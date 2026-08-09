<?php

namespace App\Services;

use App\Enums\AnomalyFlagReason;
use App\Enums\GeoStatus;
use App\Enums\OvertimeCalculationState;
use App\Enums\WorkdayStatus;
use App\Jobs\CalculateOvertime;
use App\Models\Workday;
use App\Services\Overtime\OvertimeExcessPolicyResolver;
use App\Services\Overtime\ShiftExcess;
use App\Support\Duration;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolls the raw attendance data for a given day — marks, the scheduled shift
 * and any approved leave — into one {@see Workday} row per employee, deriving
 * the status, worked/extra/missing time and the in/out shift deltas.
 *
 * The heavy lifting is a single set-based SQL query so a whole organization's
 * day can be computed in one pass. Time math relies on MySQL's TIMEDIFF/TIME
 * functions.
 *
 * The two shift excesses and the calculated overtime (OHC) are the exception:
 * they are computed in PHP by {@see ShiftExcess}, because clock-time arithmetic
 * cannot express a shift that runs past midnight and because they have to be
 * able to say "not computed" where SQL would hand back a zero.
 *
 * **Every pass is idempotent.** A day is written through an upsert keyed on the
 * `(user_id, date)` unique index, so re-running produces the same row rather
 * than a second one, and the same figures whenever the inputs have not moved.
 * That is what lets {@see CalculateOvertime} be re-run freely for a backfill or
 * after a correction.
 *
 * **The write set is the safety property.** Every column this class writes is
 * listed explicitly in {@see WorkdayCalculator::calculatedAttributes()}, and the
 * human-decision columns (`overtime_decided_at`, `overtime_decided_value`) are
 * not among them — so no recalculation, however triggered, can erase or
 * fabricate a decision. The engine's own verdict is an
 * {@see OvertimeCalculationState}, an enum with no approved case (PRD §7.2).
 *
 * **Anomaly flags are computed the same way (PRD §7.4).** Every pass derives
 * {@see AnomalyFlagReason}s from the same row this class already read, and
 * because they are recomputed rather than stored independently, a flag whose
 * cause is corrected disappears on the next recalculation with nobody having
 * to clear it. The one exception to the per-row shape is the weekly-volume
 * flag, which weighs this day's own newly derived figure against the other
 * days of its week already persisted — see
 * {@see WorkdayCalculator::weeklyOtherDaysSecondsByUser()}.
 */
class WorkdayCalculator
{
    public function __construct(
        private LegalHourLimits $legalHourLimits,
        private OvertimeExcessPolicyResolver $excessPolicyResolver,
        private OrganizationSettings $organizationSettings,
    ) {}

    /**
     * Compute the workdays for every employee with attendance or a scheduled
     * shift on the given date, creating the rows that do not exist yet and
     * updating the ones that do.
     *
     * @param  int|null  $organizationId  Restrict to one tenant. Null processes every organization, which only the seeder and single-tenant callers want.
     * @param  array<int, int>|null  $userIds  Restrict to these employees.
     */
    public function calculateDate(DateTimeInterface $date, ?int $organizationId = null, ?array $userIds = null): bool
    {
        return $this->runForDate($date, $organizationId, $userIds, onlyComputedDays: false);
    }

    /**
     * Recompute only the days already computed for the given date — the shape
     * an event-driven recalculation wants.
     *
     * A leave being approved or a shift assignment being edited says something
     * about days the register has already rolled up; it is not an instruction to
     * backfill every day in the affected range that nobody ever computed. That
     * backfill is a deliberate act, and it goes through
     * {@see WorkdayCalculator::calculateDate()}.
     *
     * @param  array<int, int>|null  $userIds  Restrict to these employees.
     */
    public function recalculateComputedDate(DateTimeInterface $date, ?int $organizationId = null, ?array $userIds = null): bool
    {
        return $this->runForDate($date, $organizationId, $userIds, onlyComputedDays: true);
    }

    /**
     * @param  array<int, int>|null  $userIds
     */
    private function runForDate(DateTimeInterface $date, ?int $organizationId, ?array $userIds, bool $onlyComputedDays): bool
    {
        $success = true;

        // Resolved from the day being computed, never from today, and stamped
        // onto every row so the rule each day was judged against stays
        // readable after the law moves on.
        $legalHourLimitId = $this->legalHourLimits->on(Carbon::parse($date))->id;
        $dateString = Carbon::parse($date)->toDateString();

        $query = $this->getWorkdayQuery($date, organizationId: $organizationId, userIds: $userIds);

        if ($onlyComputedDays) {
            $query->whereNotNull('workdays.id');
        }

        $query->chunk(100, function ($workdays) use (&$success, $legalHourLimitId, $date, $dateString): void {
            $weeklyOtherDaysSeconds = $this->weeklyOtherDaysSecondsByUser(
                $workdays->pluck('user_id')->unique()->all(),
                $date,
                $dateString,
            );

            $rows = $workdays->map(fn ($workday): array => [
                'date' => $workday->date,
                'user_id' => $workday->user_id,
                'organization_id' => $workday->organization_id,
                'company_id' => $workday->company_id,
                ...$this->calculatedAttributes($workday, $legalHourLimitId, $weeklyOtherDaysSeconds[$workday->user_id] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if ($rows === []) {
                return;
            }

            // Keyed on the (user_id, date) unique index, so a date already
            // processed is updated in place rather than duplicated. The update
            // set is read off the payload itself — everything written except the
            // key and `created_at`, since a recalculation is not a new row — so
            // it cannot drift from what the engine actually produces, and the
            // `overtime_decided_*` columns stay out of it for the only reason
            // that matters: they are never in the payload to begin with.
            $updatable = array_values(array_diff(
                array_keys($rows[array_key_first($rows)]),
                ['user_id', 'date', 'created_at'],
            ));

            if (! Workday::query()->upsert($rows, ['user_id', 'date'], $updatable)) {
                $success = false;
            }
        });

        return $success;
    }

    /**
     * Recompute a single workday in place (e.g. after a mark modification).
     */
    public function recalculateWorkday(Workday $workday): bool
    {
        $legalHourLimitId = $this->legalHourLimits->on(Carbon::parse($workday->date))->id;
        $dateString = Carbon::parse($workday->date)->toDateString();

        $weeklyOtherDaysSeconds = $this->weeklyOtherDaysSecondsByUser([$workday->user_id], $workday->date, $dateString);

        $data = $this->getWorkdayQuery($workday->date, workdayId: $workday->id)
            ->get()
            ->map(fn ($row): array => $this->calculatedAttributes($row, $legalHourLimitId, $weeklyOtherDaysSeconds[$workday->user_id] ?? 0))
            ->first();

        if ($data === null) {
            return false;
        }

        return $workday->update($data);
    }

    /**
     * Everything the engine derives for one day, and the whole of what it is
     * allowed to write.
     *
     * Both write paths — the set-based upsert and the single-row recalculation —
     * go through this one method, so the columns a recalculation can move are
     * the same however it was triggered. The absences matter as much as the
     * entries: `overtime_decided_at` and `overtime_decided_value` are not here,
     * which is why a human decision survives every recalculation, and
     * `overtime_state` can only ever hold what {@see OvertimeCalculationState}
     * can express — never an approved or payable day (PRD §7.2).
     *
     * @return array<string, mixed>
     */
    protected function calculatedAttributes(\stdClass $row, ?int $legalHourLimitId, int $weeklyOtherDaysSeconds = 0): array
    {
        $excess = $this->calculateShiftExcess($row);
        $status = $this->getStatus($row);

        return [
            'premise_id' => $row->premise_id,
            'mark_in_at' => $row->mark_in_at,
            'mark_out_at' => $row->mark_out_at,
            'mark_in_id' => $row->mark_in_id,
            'mark_out_id' => $row->mark_out_id,
            'leave_id' => $row->leave_id,
            'shift_start_time' => $row->shift_start_time,
            'shift_end_time' => $row->shift_end_time,
            'shift_id' => $row->shift_id,
            'legal_hour_limit_id' => $legalHourLimitId,
            'in_time_difference' => $row->in_time_difference,
            'out_time_difference' => $row->out_time_difference,
            'worked_time' => $this->calculateWorkedTime($row),
            'status' => $status,
            'extra_time' => $row->extra_time,
            'missing_time' => $row->missing_time,
            ...$excess,
            'overtime_state' => OvertimeCalculationState::forCalculatedOvertime($excess['calculated_overtime'])->value,
            'overtime_calculated_at' => now(),
            'anomaly_flags' => $this->encodeAnomalyFlags(
                $this->calculateAnomalyFlags($row, $status, $excess['calculated_overtime'], $weeklyOtherDaysSeconds),
            ),
        ];
    }

    /**
     * The set-based query joining users to their marks, scheduled shift day and
     * approved leave for the date, producing one candidate workday row per user.
     *
     * ShiftDay weekdays are 0=Monday … 6=Sunday, so the join keys off
     * `format('N') - 1` (ISO day, 1=Monday) rather than Carbon's `dayOfWeek`.
     *
     * @param  array<int, int>|null  $userIds
     */
    protected function getWorkdayQuery(
        DateTimeInterface $date,
        ?int $workdayId = null,
        ?int $organizationId = null,
        ?array $userIds = null,
    ): Builder {
        $dateString = Carbon::parse($date)->toDateString();
        $weekday = (int) Carbon::parse($date)->format('N') - 1;

        $query = DB::table('users')
            ->leftJoin('marks as mark_in', function ($join) use ($date): void {
                $join->on('users.id', '=', 'mark_in.user_id')
                    ->whereDate('mark_in.date_time', '=', $date)
                    ->where('mark_in.type', '=', 'in')
                    ->whereNull('mark_in.deleted_at');
            })
            ->leftJoin('marks as mark_out', function ($join) use ($date): void {
                $join->on('users.id', '=', 'mark_out.user_id')
                    ->whereDate('mark_out.date_time', '=', $date)
                    ->where('mark_out.type', '=', 'out')
                    ->whereNull('mark_out.deleted_at');
            })
            ->leftJoin('shift_assignments', function ($join) use ($date): void {
                $join->on('users.id', '=', 'shift_assignments.user_id')
                    ->whereDate('shift_assignments.start_date', '<=', $date)
                    ->where(function ($query) use ($date): void {
                        $query->whereNull('shift_assignments.end_date')
                            ->orWhereDate('shift_assignments.end_date', '>=', $date);
                    });
            })
            ->leftJoin('shift_days', function ($join) use ($weekday): void {
                $join->on('shift_assignments.shift_id', '=', 'shift_days.shift_id')
                    ->where('shift_days.weekday', '=', $weekday)
                    ->where('shift_days.is_free', '=', false);
            })
            ->leftJoin('leaves', function ($join) use ($date): void {
                $join->on('users.id', '=', 'leaves.user_id')
                    ->whereRaw('leaves.id = (select MIN(id) from leaves where user_id = users.id and status = "approved" and start_date <= ? and end_date >= ? limit 1)', [$date, $date]);
            })
            ->leftJoin('shifts', 'shifts.id', '=', 'shift_days.shift_id')
            ->leftJoin('workdays', function ($join) use ($date): void {
                $join->on('users.id', '=', 'workdays.user_id')
                    ->whereDate('workdays.date', '=', $date);
            })
            ->whereNotNull('users.organization_id')
            ->where(function ($query): void {
                $query->whereNotNull('mark_in.date_time')
                    ->orWhereNotNull('mark_out.date_time')
                    ->orWhereNotNull('shift_days.id');
            })
            ->selectRaw('? as date', [$dateString])
            ->addSelect([
                'users.id as user_id',
                'users.company_id as company_id',
                'users.organization_id as organization_id',
                'users.premise_id as premise_id',
                'users.contract_start_date as contract_start_date',
                'users.contract_end_date as contract_end_date',
                'mark_in.date_time as mark_in_at',
                'mark_out.date_time as mark_out_at',
                'mark_in.id as mark_in_id',
                'mark_out.id as mark_out_id',
                'mark_in.geo_status as mark_in_geo_status',
                'mark_out.geo_status as mark_out_geo_status',
                'shift_days.start_time as shift_start_time',
                'shift_days.end_time as shift_end_time',
                'shift_days.shift_id',
                'shift_days.lunch_start_time as lunch_start_time',
                'shift_days.lunch_end_time as lunch_end_time',
                'leaves.id as leave_id',
                DB::raw('TIMEDIFF(TIME(mark_in.date_time), shift_days.start_time) as in_time_difference'),
                DB::raw('TIMEDIFF(TIME(mark_out.date_time), shift_days.end_time) as out_time_difference'),
                DB::raw("
                    CASE
                        WHEN shift_days.end_time IS NULL OR shift_days.start_time IS NULL
                            THEN TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time))
                        WHEN TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time)) > TIMEDIFF(shift_days.end_time, shift_days.start_time)
                            THEN TIMEDIFF(
                                    TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time)),
                                    TIMEDIFF(shift_days.end_time, shift_days.start_time)
                                 )
                        ELSE '00:00:00'
                    END as extra_time
                "),
                DB::raw("
                    CASE
                        WHEN TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time)) < TIMEDIFF(shift_days.end_time, shift_days.start_time)
                            THEN TIMEDIFF(
                                    TIMEDIFF(shift_days.end_time, shift_days.start_time),
                                    TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time))
                                 )
                        ELSE '00:00:00'
                    END as missing_time
                "),
            ])
            ->orderBy('users.id')
            ->distinct();

        // A workday id means we are recomputing that single row. Otherwise every
        // candidate is returned, computed or not: the bulk pass upserts, so an
        // existing row is updated rather than skipped.
        if ($workdayId !== null) {
            $query->where('workdays.id', $workdayId);
        }

        // The tenant boundary. `users.organization_id` is the one the whole
        // query hangs off — marks, assignments and leaves all reach the row
        // through the employee — so constraining it here is what keeps one
        // tenant's pass from reading, let alone writing, another's day.
        if ($organizationId !== null) {
            $query->where('users.organization_id', $organizationId);
        }

        if ($userIds !== null) {
            $query->whereIn('users.id', $userIds);
        }

        return $query;
    }

    /**
     * Derive the attendance status from the presence of marks, a shift and a
     * leave for the day.
     */
    protected function getStatus(\stdClass $workday): ?string
    {
        // An approved leave justifies the whole day.
        if ($workday->leave_id !== null) {
            return WorkdayStatus::Justified->value;
        }

        // Marks with no scheduled shift are irregular attendance.
        if (
            $workday->shift_id === null
            && ($workday->mark_in_id !== null || $workday->mark_out_id !== null)
        ) {
            return WorkdayStatus::Irregular->value;
        }

        // Both marks against a scheduled shift is a regular workday.
        if ($workday->mark_in_id !== null && $workday->mark_out_id !== null && $workday->shift_id !== null) {
            return WorkdayStatus::Regular->value;
        }

        // A scheduled shift with no marks at all is an absence.
        if ($workday->mark_in_id === null && $workday->mark_out_id === null && $workday->shift_id !== null) {
            return WorkdayStatus::Absent->value;
        }

        // Only one of the two marks: an incomplete day.
        if ($workday->mark_in_id !== null || $workday->mark_out_id !== null) {
            return WorkdayStatus::Incomplete->value;
        }

        return null;
    }

    /**
     * The day's two shift excesses and the calculated overtime (OHC) they
     * produce under the organization's policy (PRD §7.2).
     *
     * Both excesses are stored whatever the policy says, so enabling early
     * arrival later changes only what future days count and never asks for a
     * recalculation of history. All three are null together when the day gives
     * no basis to claim overtime.
     *
     * @return array{pre_shift_excess: string|null, post_shift_excess: string|null, calculated_overtime: string|null}
     */
    protected function calculateShiftExcess(\stdClass $workday): array
    {
        $excess = ShiftExcess::forWorkdayRow($workday);

        if ($excess === null) {
            return [
                'pre_shift_excess' => null,
                'post_shift_excess' => null,
                'calculated_overtime' => null,
            ];
        }

        return [
            'pre_shift_excess' => $excess->preShiftExcess(),
            'post_shift_excess' => $excess->postShiftExcess(),
            'calculated_overtime' => $excess->calculatedOvertime(
                $this->excessPolicyResolver->for($workday),
            ),
        ];
    }

    /**
     * The anomaly flags for the day (PRD §7.4): reasons the underlying data is
     * not trustworthy enough to pay from, which block the day from reaching
     * approved until a human has looked at it. Never blocks this calculation
     * or the marks/shifts it reads (Resolución 38 art. 45.2) — the flags are
     * only ever a value written alongside the rest of the row.
     *
     * The first two are read straight off the status already computed for the
     * day rather than re-deriving the same condition a second way.
     *
     * @return array<int, AnomalyFlagReason>
     */
    protected function calculateAnomalyFlags(\stdClass $row, ?string $status, ?string $calculatedOvertime, int $weeklyOtherDaysSeconds): array
    {
        $reasons = [];

        if ($status === WorkdayStatus::Irregular->value) {
            $reasons[] = AnomalyFlagReason::NoAssignedShift;
        }

        if ($status === WorkdayStatus::Incomplete->value) {
            $reasons[] = AnomalyFlagReason::IncompleteMarks;
        }

        if ($this->contractNotActive($row)) {
            $reasons[] = AnomalyFlagReason::ContractNotActive;
        }

        if ($row->mark_in_geo_status === GeoStatus::Outside->value || $row->mark_out_geo_status === GeoStatus::Outside->value) {
            $reasons[] = AnomalyFlagReason::OutsideGeofence;
        }

        $weeklyTotalSeconds = $weeklyOtherDaysSeconds + (Duration::tryFrom($calculatedOvertime)?->seconds ?? 0);
        $thresholdSeconds = (int) round($this->organizationSettings->overtimeWeeklyAnomalyThresholdHours($row->organization_id) * 3600);

        if ($weeklyTotalSeconds > $thresholdSeconds) {
            $reasons[] = AnomalyFlagReason::PeriodVolumeExceeded;
        }

        return $reasons;
    }

    /**
     * Whether the employee's contract does not cover the day's own date. Only
     * asserted when a boundary is actually set — a contract with neither date
     * recorded gives no basis to call it inactive, so it is left unflagged
     * rather than treated as a missing-data anomaly.
     */
    private function contractNotActive(\stdClass $row): bool
    {
        if ($row->contract_start_date === null && $row->contract_end_date === null) {
            return false;
        }

        $date = Carbon::parse($row->date);

        if ($row->contract_start_date !== null && $date->lt(Carbon::parse($row->contract_start_date))) {
            return true;
        }

        if ($row->contract_end_date !== null && $date->gt(Carbon::parse($row->contract_end_date))) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, AnomalyFlagReason>  $reasons
     */
    private function encodeAnomalyFlags(array $reasons): ?string
    {
        if ($reasons === []) {
            return null;
        }

        return json_encode(array_map(fn (AnomalyFlagReason $reason): string => $reason->value, $reasons));
    }

    /**
     * Each given user's total calculated overtime (in seconds) for the ISO
     * week containing $date, over every other already-computed day in that
     * week. The date being computed is excluded because its own contribution
     * is added by the caller from the figure this same pass just derived,
     * never from a stale persisted value.
     *
     * Batched per chunk rather than queried per row, matching the set-based
     * shape of the rest of this class: every row in a chunk shares the same
     * $date and therefore the same week.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    private function weeklyOtherDaysSecondsByUser(array $userIds, DateTimeInterface $date, string $excludeDateString): array
    {
        if ($userIds === []) {
            return [];
        }

        $week = Carbon::parse($date);

        return DB::table('workdays')
            ->select('user_id', DB::raw('SUM(TIME_TO_SEC(calculated_overtime)) as total_seconds'))
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [
                $week->clone()->startOfWeek(Carbon::MONDAY)->toDateString(),
                $week->clone()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ])
            ->where('date', '!=', $excludeDateString)
            ->whereNotNull('calculated_overtime')
            ->groupBy('user_id')
            ->pluck('total_seconds', 'user_id')
            ->map(fn ($seconds): int => (int) $seconds)
            ->all();
    }

    /**
     * Worked time (HH:MM:SS) between the in and out marks, excluding the
     * scheduled lunch break when one is defined.
     */
    protected function calculateWorkedTime(\stdClass $workday): string
    {
        $markIn = $this->toCarbon($workday->mark_in_at);
        $markOut = $this->toCarbon($workday->mark_out_at);

        if ($markIn === null || $markOut === null) {
            return '00:00:00';
        }

        $lunchStart = $this->toCarbon($workday->lunch_start_time ?? null);
        $lunchEnd = $this->toCarbon($workday->lunch_end_time ?? null);

        $seconds = $markIn->diffInSeconds($markOut);

        if ($lunchStart !== null && $lunchEnd !== null) {
            $seconds -= $lunchStart->diffInSeconds($lunchEnd);
        }

        return gmdate('H:i:s', (int) $seconds);
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        return is_string($value) ? Carbon::parse($value) : null;
    }
}
