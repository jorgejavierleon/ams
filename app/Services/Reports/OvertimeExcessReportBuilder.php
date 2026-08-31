<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\LegalHourLimits;
use App\Services\Overtime\OvertimePayBucketBreakdown;
use App\Services\Overtime\OvertimePayBucketClassifier;
use App\Support\CurrentOrganization;
use App\Support\Duration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the "Excesos de Jornada y HHEE" report's weeks (RF-1, KOL-24) —
 * Buk's "reporte de excesos de jornada semanal" equivalent: overtime grouped
 * by week, each employee split into what is pactada (authorised, in its pay
 * bucket per {@see OvertimePayBucketClassifier}, KOL-12) and what is no
 * pactada (KOL-46's unauthorised remainder), for a single employee or a
 * consolidated selection (AC #1) — the same row shape serves both, so there
 * is no special-casing on how many employees were selected.
 *
 * **Weekly, not period-wide.** Unlike {@see PayrollSummaryReportBuilder},
 * which classifies a whole period at once, this calls
 * {@see OvertimePayBucketClassifier::forPeriod()} once per Monday–Sunday
 * week, because AC #4's legal-cap flag needs a week-scoped total to compare
 * against {@see LegalHourLimits::forWeekOf()}, not a period-wide one.
 *
 * **Weeks straddle whole, not the requested range.** The requested period is
 * expanded to whole Monday–Sunday weeks (AC #5), the same rule
 * {@see WeeklyDetailReportBuilder} and {@see LegalHourLimits::forWeekOf()}
 * already use — a week that starts or ends outside the nominal period still
 * renders in full, so a discrepancy at the edge of a month is never split
 * across two partial rows.
 *
 * **The cap is evaluated per employee per week, never aggregated.**
 * Código del Trabajo art. 31's 12h/week overtime ceiling is a per-worker
 * limit — a consolidated selection of 50 employees each individually within
 * the cap must never read as "over the limit" because their hours summed
 * together exceed 12h. {@see self::row()} computes `capExceeded` from that
 * one employee's own week total; the week only aggregates how many rows
 * breached it, for a consolidated selection to scan at a glance.
 */
class OvertimeExcessReportBuilder
{
    public function __construct(
        private OvertimePayBucketClassifier $classifier,
        private LegalHourLimits $legalHourLimits,
    ) {}

    /**
     * @param  list<int>  $userIds
     * @return array{weeks: list<array<string, mixed>>}
     */
    public function build(Carbon $start, Carbon $end, array $userIds): array
    {
        if ($userIds === []) {
            return ['weeks' => []];
        }

        $employeesById = User::query()
            ->where('organization_id', CurrentOrganization::id())
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'rut'])
            ->keyBy('id');

        if ($employeesById->isEmpty()) {
            return ['weeks' => []];
        }

        $scopedUserIds = array_values(array_map(intval(...), $employeesById->keys()->all()));

        $periodStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $periodEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $weekStart = $periodStart->copy();

        while ($weekStart->lte($periodEnd)) {
            $weekEnd = $weekStart->copy()->addDays(6);

            $weeks[] = $this->week($weekStart, $weekEnd, $employeesById, $scopedUserIds);

            $weekStart = $weekStart->copy()->addDays(7);
        }

        return ['weeks' => $weeks];
    }

    /**
     * @param  Collection<int, User>  $employeesById
     * @param  list<int>  $scopedUserIds
     * @return array<string, mixed>
     */
    private function week(Carbon $weekStart, Carbon $weekEnd, Collection $employeesById, array $scopedUserIds): array
    {
        $breakdowns = $this->classifier->forPeriod($weekStart, $weekEnd, $scopedUserIds);
        $capHours = $this->legalHourLimits->forWeekOf($weekStart)->max_overtime_weekly_hours;
        $legalReference = $this->legalHourLimits->forWeekOf($weekStart)->legal_reference;
        $capSeconds = (int) round($capHours * 3600);

        $rows = array_values($employeesById
            ->map(fn (User $employee): array => $this->row(
                $employee,
                $breakdowns->get($employee->id) ?? new OvertimePayBucketBreakdown(
                    $employee->id,
                    Duration::zero(),
                    Duration::zero(),
                    Duration::zero(),
                    Duration::zero(),
                ),
                $capSeconds,
            ))
            ->all());

        return [
            'start' => $weekStart->format('Y-m-d'),
            'end' => $weekEnd->format('Y-m-d'),
            'weeklyOvertimeCapHours' => $capHours,
            'legalReference' => $legalReference,
            'employeesOverCapCount' => count(array_filter($rows, fn (array $row): bool => $row['capExceeded'])),
            'rows' => $rows,
            'total' => $this->total($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $employee, OvertimePayBucketBreakdown $breakdown, int $capSeconds): array
    {
        $ordinaryDay = $breakdown->ordinaryDayHours;
        $sundayOrHoliday = $breakdown->sundayOrHolidayHours;
        $compensatedInRestDays = $breakdown->compensatedInRestDaysHours;
        $unauthorized = $breakdown->unauthorizedHours;

        $payableTotalSeconds = $ordinaryDay->seconds + $sundayOrHoliday->seconds + $compensatedInRestDays->seconds;
        $totalSeconds = $payableTotalSeconds + $unauthorized->seconds;

        return [
            'userId' => $employee->id,
            'name' => $employee->name,
            'rut' => $employee->formatted_rut,
            'ordinaryDayHours' => $ordinaryDay->toTimeString(),
            'sundayOrHolidayHours' => $sundayOrHoliday->toTimeString(),
            'compensatedInRestDaysHours' => $compensatedInRestDays->toTimeString(),
            'payableTotalHours' => Duration::fromSeconds($payableTotalSeconds)->toTimeString(),
            'unauthorizedHours' => $unauthorized->toTimeString(),
            'totalHours' => Duration::fromSeconds($totalSeconds)->toTimeString(),
            'capExceeded' => $totalSeconds > $capSeconds,
        ];
    }

    /**
     * The week's aggregate across the selected employees — informational
     * (an accountant's payable subtotal), never compared against the legal
     * cap, which is evaluated per employee in {@see self::row()}.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function total(array $rows): array
    {
        $durationColumns = [
            'ordinaryDayHours', 'sundayOrHolidayHours', 'compensatedInRestDaysHours',
            'payableTotalHours', 'unauthorizedHours', 'totalHours',
        ];

        $total = ['employeeCount' => count($rows)];

        foreach ($durationColumns as $column) {
            $seconds = array_sum(array_map(
                fn (array $row): int => Duration::fromTimeString($row[$column])->seconds,
                $rows,
            ));
            $total[$column] = Duration::fromSeconds($seconds)->toTimeString();
        }

        return $total;
    }
}
