<?php

namespace App\Services\Reports;

use App\Enums\LeaveType;
use App\Http\Controllers\Dt\ReportController;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use App\Models\Workday;
use App\Services\Overtime\OvertimePayBucketBreakdown;
use App\Services\Overtime\OvertimePayBucketClassifier;
use App\Support\CurrentOrganization;
use App\Support\Duration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the per-employee payroll summary for a period (quincena or mes) that
 * RF-1's reports and the Nubox export read from (PRD §2.3.3, KOL-13).
 *
 * Every figure is read, never recomputed, from data that already exists:
 * worked/missing/lateness time comes straight off {@see Workday} rows;
 * overtime pay buckets come from {@see OvertimePayBucketClassifier} (KOL-12,
 * itself built on the approved-only KOL-49 dataset); Sundays/holidays worked
 * come from {@see SundaysReportService}; and the justified/unjustified
 * absence split plus the paid/non-paid days breakdown come from
 * {@see AttendanceReportService}'s own day-by-day resolution — reused rather
 * than reimplemented, so the two reports can never disagree for the same
 * employee and date (KOL-13 AC #2).
 *
 * **Organization scoping.** {@see User} carries no organization global scope
 * (see {@see ReportController}), so an id passed in
 * explicitly is first intersected against the current organization's own
 * employees here, before any other query runs. Every other model this class
 * reads from ({@see Workday}, leaves, overtime authorizations) is
 * organization-scoped by {@see BelongsToOrganization},
 * which is a second, independent guarantee that a cross-tenant id can never
 * surface real figures even if the first filter were somehow bypassed.
 */
class PayrollPeriodSummaryService
{
    public function __construct(
        private AttendanceReportService $attendance,
        private SundaysReportService $sundays,
        private OvertimePayBucketClassifier $overtime,
    ) {}

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, PayrollPeriodSummary>
     */
    public function build(Carbon $start, Carbon $end, array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        $scopedUserIds = array_values(array_map(
            intval(...),
            User::query()
                ->where('organization_id', CurrentOrganization::id())
                ->whereIn('id', $userIds)
                ->pluck('id')
                ->all(),
        ));

        if ($scopedUserIds === []) {
            return collect();
        }

        $workdayTotals = $this->workdayTotalsByUser($start, $end, $scopedUserIds);
        $overtime = $this->overtime->forPeriod($start, $end, $scopedUserIds);
        $sundaysWorked = $this->sundaysWorkedByUser($start, $end, $scopedUserIds);
        $absences = $this->absenceBreakdownByUser($start, $end, $scopedUserIds);

        return collect($scopedUserIds)->mapWithKeys(function (int $userId) use ($workdayTotals, $overtime, $sundaysWorked, $absences): array {
            $totals = $workdayTotals[$userId] ?? ['worked' => 0, 'missing' => 0, 'lateness' => 0];
            $absence = $absences[$userId] ?? $this->emptyAbsenceBreakdown();

            return [$userId => new PayrollPeriodSummary(
                userId: $userId,
                workedHours: Duration::fromSeconds($totals['worked']),
                nonWorkedHours: Duration::fromSeconds($totals['missing']),
                totalLateness: Duration::fromSeconds($totals['lateness']),
                overtime: $overtime->get($userId) ?? new OvertimePayBucketBreakdown(
                    userId: $userId,
                    ordinaryDayHours: Duration::zero(),
                    sundayOrHolidayHours: Duration::zero(),
                    compensatedInRestDaysHours: Duration::zero(),
                    unauthorizedHours: Duration::zero(),
                ),
                justifiedAbsenceDays: $absence['justified'],
                unjustifiedAbsenceDays: $absence['unjustified'],
                sundaysAndHolidaysWorked: $sundaysWorked[$userId] ?? 0,
                paidDays: new PaidDaysBreakdown(...$absence['paid']),
                nonPaidDays: new NonPaidDaysBreakdown(...$absence['nonPaid']),
            )];
        });
    }

    /**
     * workedHours, nonWorkedHours and totalLateness in one bulk pass over the
     * period's {@see Workday} rows — bounded regardless of how many employees
     * are requested.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{worked: int, missing: int, lateness: int}>
     */
    private function workdayTotalsByUser(Carbon $start, Carbon $end, array $userIds): array
    {
        $totals = [];

        Workday::query()
            ->whereIn('user_id', $userIds)
            ->betweenDates($start, $end)
            ->get(['user_id', 'worked_time', 'missing_time', 'in_time_difference'])
            ->each(function (Workday $workday) use (&$totals): void {
                $totals[$workday->user_id] ??= ['worked' => 0, 'missing' => 0, 'lateness' => 0];
                $totals[$workday->user_id]['worked'] += Duration::tryFrom($workday->worked_time)->seconds ?? 0;
                $totals[$workday->user_id]['missing'] += Duration::tryFrom($workday->missing_time)->seconds ?? 0;
                $totals[$workday->user_id]['lateness'] += $this->latenessSeconds($workday->in_time_difference);
            });

        return $totals;
    }

    /**
     * `in_time_difference` is `TIMEDIFF(mark_in, shift_start)`: negative when
     * the employee clocked in early, positive when late. Only the late half
     * counts as lateness — an early arrival contributes nothing here.
     */
    private function latenessSeconds(?string $inTimeDifference): int
    {
        if ($inTimeDifference === null || str_starts_with($inTimeDifference, '-')) {
            return 0;
        }

        return Duration::fromTimeString($inTimeDifference)->seconds;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, int> Sundays and holidays worked per user id
     */
    private function sundaysWorkedByUser(Carbon $start, Carbon $end, array $userIds): array
    {
        return collect($this->sundays->build($start, $end, $userIds))
            ->pluck('total', 'userId')
            ->all();
    }

    /**
     * The justified/unjustified absence counts and the paid/non-paid days
     * split, both derived from {@see AttendanceReportService}'s own per-day
     * rows rather than a second definition of what counts as an absence
     * (KOL-13 AC #2).
     *
     * A day is either:
     * - attended (`absence === null`): a paid worked day;
     * - justified by an approved leave: bucketed by {@see LeaveType} into
     *   paid days (vacation, paid leave) or non-paid days (medical, unpaid);
     * - justified by a free shift day or a holiday with no leave: counted in
     *   `justifiedAbsenceDays` only — it is not a day the employer owes pay
     *   for or docks pay from, so it lands in neither days breakdown; or
     * - unjustified: a non-paid day.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{
     *     justified: int,
     *     unjustified: int,
     *     paid: array{workedDays: int, vacationDays: int, paidLeaveDays: int},
     *     nonPaid: array{unjustifiedAbsenceDays: int, medicalLeaveDays: int, unpaidLeaveDays: int},
     * }>
     */
    private function absenceBreakdownByUser(Carbon $start, Carbon $end, array $userIds): array
    {
        $breakdowns = [];

        foreach ($this->attendance->build($start, $end, $userIds) as $block) {
            $paid = ['workedDays' => 0, 'vacationDays' => 0, 'paidLeaveDays' => 0];
            $nonPaid = ['unjustifiedAbsenceDays' => 0, 'medicalLeaveDays' => 0, 'unpaidLeaveDays' => 0];
            $justified = 0;
            $unjustified = 0;

            foreach ($block['rows'] as $row) {
                if ($row['absence'] === null) {
                    $paid['workedDays']++;

                    continue;
                }

                if ($row['absence'] === 'unjustified') {
                    $unjustified++;
                    $nonPaid['unjustifiedAbsenceDays']++;

                    continue;
                }

                $justified++;
                $observation = $row['observation'];

                if ($observation !== null && $observation['kind'] === 'leave' && isset($observation['type'])) {
                    $this->bucketLeaveType(LeaveType::from($observation['type']), $paid, $nonPaid);
                }
            }

            $breakdowns[$block['userId']] = [
                'justified' => $justified,
                'unjustified' => $unjustified,
                'paid' => $paid,
                'nonPaid' => $nonPaid,
            ];
        }

        return $breakdowns;
    }

    /**
     * @param  array{workedDays: int, vacationDays: int, paidLeaveDays: int}  $paid
     * @param  array{unjustifiedAbsenceDays: int, medicalLeaveDays: int, unpaidLeaveDays: int}  $nonPaid
     */
    private function bucketLeaveType(LeaveType $type, array &$paid, array &$nonPaid): void
    {
        match ($type) {
            LeaveType::Vacation => $paid['vacationDays']++,
            LeaveType::Paid => $paid['paidLeaveDays']++,
            LeaveType::Medical => $nonPaid['medicalLeaveDays']++,
            LeaveType::Unpaid, LeaveType::Other => $nonPaid['unpaidLeaveDays']++,
        };
    }

    /**
     * @return array{
     *     justified: int,
     *     unjustified: int,
     *     paid: array{workedDays: int, vacationDays: int, paidLeaveDays: int},
     *     nonPaid: array{unjustifiedAbsenceDays: int, medicalLeaveDays: int, unpaidLeaveDays: int},
     * }
     */
    private function emptyAbsenceBreakdown(): array
    {
        return [
            'justified' => 0,
            'unjustified' => 0,
            'paid' => ['workedDays' => 0, 'vacationDays' => 0, 'paidLeaveDays' => 0],
            'nonPaid' => ['unjustifiedAbsenceDays' => 0, 'medicalLeaveDays' => 0, 'unpaidLeaveDays' => 0],
        ];
    }
}
