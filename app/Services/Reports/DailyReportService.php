<?php

namespace App\Services\Reports;

use App\Enums\LeaveStatus;
use App\Enums\MarkType;
use App\Enums\ShiftType;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Mark;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the "Reporte de jornada diaria" required by Resolución 38, Art. 27 b):
 * a per-worker, week-by-week grid of contracted vs. worked hours. For each day
 * it resolves the pacted ordinary journey and lunch, the first IN / last OUT
 * marks, the shortfall ("Tiempo faltante", always ≤ 0) and the overtime
 * ("Tiempo extra", always ≥ 0), and a human observation.
 *
 * Every week closes with a totals line (Art. 27 b.12): the automatic per-column
 * result, signed "+"/"-", ending in the weekly compensation figure. To make the
 * weekly compensation meaningful the requested range is expanded to whole ISO
 * weeks (Monday–Sunday), matching the certified legacy report.
 *
 * The calculation rules (late arrival, early departure, early ingress, overtime
 * after the ordinary journey, whole shift missing when no marks) are ported from
 * the legacy {@see DtDailyReport} the DT authorized.
 */
class DailyReportService
{
    /**
     * @param  list<int>  $userIds
     * @return list<array{
     *     employee: string,
     *     employer: string|null,
     *     premise: string|null,
     *     hasFlexibleBand: bool,
     *     exceptionalCycle: string|null,
     *     weeks: list<array{days: list<array<string, mixed>>, totals: array<string, string>}>,
     * }>
     */
    public function build(Carbon $start, Carbon $end, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        // Whole ISO weeks so each week's compensation totals are complete.
        $periodStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $periodEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $users = User::query()
            ->whereIn('id', $userIds)
            ->with(['company:id,social_reason,rut', 'premise:id,name'])
            ->orderBy('name')
            ->get();

        $marks = $this->marksByUser($userIds, $periodStart, $periodEnd);
        $leaves = $this->approvedLeavesByUser($userIds, $periodStart, $periodEnd);
        $assignments = $this->shiftAssignmentsByUser($userIds, $periodStart, $periodEnd);
        $holidays = $this->holidaysByDate($periodStart, $periodEnd);

        $dates = [];

        foreach (CarbonPeriod::create($periodStart->copy()->startOfDay(), $periodEnd->copy()->startOfDay()) as $date) {
            $dates[] = $date;
        }

        return array_values($users->map(function (User $user) use ($dates, $marks, $leaves, $assignments, $holidays): array {
            $userLeaves = $leaves->get($user->id, collect());
            $userAssignments = $assignments->get($user->id, collect());
            $userMarks = $marks[$user->id] ?? [];

            $weeks = array_values(
                collect($dates)
                    ->groupBy(fn (Carbon $date): string => $date->format('o-W'))
                    ->map(fn (Collection $weekDates): array => $this->buildWeek(
                        $weekDates, $userMarks, $userLeaves, $userAssignments, $holidays,
                    ))
                    ->all()
            );

            return [
                'employee' => $this->label($user->name, $user->formatted_rut ?? $user->rut),
                'employer' => $user->company === null
                    ? null
                    : $this->label($user->company->social_reason, $user->company->formatted_rut ?? $user->company->rut),
                'premise' => $user->premise?->name,
                'hasFlexibleBand' => false,
                'exceptionalCycle' => $this->exceptionalCycle($userAssignments),
                'weeks' => $weeks,
            ];
        })->all());
    }

    /**
     * Build one week: a day row per date plus the signed totals line (b.12).
     *
     * @param  Collection<int, Carbon>  $weekDates
     * @param  array<string, array{in: Carbon|null, out: Carbon|null}>  $userMarks
     * @param  Collection<int, Leave>  $userLeaves
     * @param  Collection<int, ShiftAssignment>  $userAssignments
     * @param  Collection<string, Holiday>  $holidays
     * @return array{days: list<array<string, mixed>>, totals: array<string, string>}
     */
    private function buildWeek(
        Collection $weekDates,
        array $userMarks,
        Collection $userLeaves,
        Collection $userAssignments,
        Collection $holidays,
    ): array {
        $days = [];
        $journeySeconds = 0;
        $marksSeconds = 0;
        $lunchSeconds = 0;
        $undertimeSeconds = 0;
        $overtimeSeconds = 0;

        foreach ($weekDates as $date) {
            $key = $date->format('Y-m-d');
            $shiftDay = $this->shiftDayFor($userAssignments, $date);
            $isFree = (bool) ($shiftDay?->is_free);
            $isWorkingDay = $shiftDay !== null && ! $isFree;
            $holiday = $holidays->get($key);
            $leave = $userLeaves->first(
                fn (Leave $leave): bool => $date->betweenIncluded($leave->start_date, $leave->end_date)
            );
            $nonWorkingDay = ! $isWorkingDay || $holiday !== null || $leave !== null;

            $dayMarks = $userMarks[$key] ?? ['in' => null, 'out' => null];
            $inMark = $dayMarks['in'];
            $outMark = $dayMarks['out'];

            $undertime = $this->undertimeSeconds($shiftDay, $isWorkingDay, $nonWorkingDay, $inMark, $outMark);
            $overtime = $this->overtimeSeconds($shiftDay, $isWorkingDay, $nonWorkingDay, $inMark, $outMark);

            if ($isWorkingDay) {
                $journeySeconds += $this->shiftDuration($shiftDay);
                $lunchSeconds += $this->diffSeconds($shiftDay->lunch_start_time, $shiftDay->lunch_end_time);
            }
            $marksSeconds += $this->diffSeconds($inMark, $outMark);
            $undertimeSeconds += $undertime;
            $overtimeSeconds += $overtime;

            $days[] = [
                // dd/mm/aa per Resolución 38, Art. 27 b.2.
                'date' => $date->format('d/m/y'),
                'journey' => $isWorkingDay ? [
                    'start' => $this->time($shiftDay->start_time),
                    'end' => $this->time($shiftDay->end_time),
                ] : null,
                'journeyMarks' => [
                    'in' => $this->time($inMark),
                    'out' => $this->time($outMark),
                ],
                'lunch' => $isWorkingDay && $shiftDay->lunch_start_time !== null && $shiftDay->lunch_end_time !== null ? [
                    'start' => $this->time($shiftDay->lunch_start_time),
                    'end' => $this->time($shiftDay->lunch_end_time),
                ] : null,
                // The system requires no lunch marks: "No aplica" (Art. 27 b.6).
                'lunchMarks' => null,
                'undertime' => $this->signedInterval($undertime),
                'overtime' => $this->signedInterval($overtime),
                // No additional mark types are captured (Art. 27 b.9).
                'otherMarks' => null,
                'observation' => $this->observation($isFree, $holiday, $leave),
            ];
        }

        return [
            'days' => $days,
            'totals' => [
                'journey' => $this->signedInterval($journeySeconds),
                'journeyMarks' => $this->signedInterval($marksSeconds),
                'lunch' => $this->signedInterval($lunchSeconds),
                'undertime' => $this->signedInterval($undertimeSeconds),
                'overtime' => $this->signedInterval($overtimeSeconds),
                'compensation' => $this->signedInterval($undertimeSeconds + $overtimeSeconds),
            ],
        ];
    }

    /**
     * "Tiempo faltante" (Art. 27 b.7): late arrival plus early departure, always
     * ≤ 0. A working day with no marks at all counts the whole shift as missing.
     */
    private function undertimeSeconds(
        ?ShiftDay $shiftDay,
        bool $isWorkingDay,
        bool $nonWorkingDay,
        ?CarbonInterface $inMark,
        ?CarbonInterface $outMark,
    ): int {
        if ($nonWorkingDay || $shiftDay === null) {
            return 0;
        }

        if ($inMark === null && $outMark === null) {
            return -$this->shiftDuration($shiftDay);
        }

        $lateBy = 0;
        if ($inMark !== null) {
            $delta = (int) $shiftDay->start_time->diffInSeconds($this->atTime($shiftDay->start_time, $inMark), false);
            $lateBy = $delta > 0 ? -$delta : 0;
        }

        $earlyDepartureBy = 0;
        if ($outMark !== null) {
            $delta = (int) $shiftDay->end_time->diffInSeconds($this->atTime($shiftDay->end_time, $outMark), false);
            $earlyDepartureBy = $delta < 0 ? $delta : 0;
        }

        return $lateBy + $earlyDepartureBy;
    }

    /**
     * "Tiempo extra" (Art. 27 b.8): early ingress plus overtime after the
     * ordinary journey, always ≥ 0. On a non-working day every worked second is
     * overtime.
     */
    private function overtimeSeconds(
        ?ShiftDay $shiftDay,
        bool $isWorkingDay,
        bool $nonWorkingDay,
        ?CarbonInterface $inMark,
        ?CarbonInterface $outMark,
    ): int {
        if ($nonWorkingDay || $shiftDay === null) {
            return $this->diffSeconds($inMark, $outMark);
        }

        if (! $isWorkingDay) {
            return 0;
        }

        $overtime = 0;
        if ($inMark !== null) {
            $delta = (int) $shiftDay->start_time->diffInSeconds($this->atTime($shiftDay->start_time, $inMark), false);
            if ($delta < 0) {
                $overtime += abs($delta);
            }
        }
        if ($outMark !== null) {
            $delta = (int) $shiftDay->end_time->diffInSeconds($this->atTime($shiftDay->end_time, $outMark), false);
            if ($delta > 0) {
                $overtime += $delta;
            }
        }

        return $overtime;
    }

    /**
     * The "Observaciones" column: a free shift day, a holiday (with its name) or
     * the leave type covering the day, in that order of precedence.
     *
     * @return array{kind: string, name?: string, type?: string}|null
     */
    private function observation(bool $isFree, ?Holiday $holiday, ?Leave $leave): ?array
    {
        if ($isFree) {
            return ['kind' => 'free'];
        }

        if ($holiday !== null) {
            return ['kind' => 'holiday', 'name' => $holiday->name];
        }

        if ($leave !== null) {
            return ['kind' => 'leave', 'type' => $leave->type->value];
        }

        return null;
    }

    /**
     * The exceptional-distribution cycle (Art. 27 b.11): the description of the
     * worker's covering exceptional shift, or null when none applies.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     */
    private function exceptionalCycle(Collection $assignments): ?string
    {
        $shift = $assignments
            ->map(fn (ShiftAssignment $assignment) => $assignment->shift)
            ->filter(fn ($shift): bool => $shift?->type === ShiftType::Exceptional)
            ->first();

        return $shift->description ?? $shift?->name;
    }

    /**
     * Resolve the shift day governing a date: the most recently started
     * assignment active on that date, matched to the weekday's shift day
     * (Monday = 0 … Sunday = 6).
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     */
    private function shiftDayFor(Collection $assignments, Carbon $date): ?ShiftDay
    {
        $weekday = $date->dayOfWeekIso - 1;

        $assignment = $assignments
            ->filter(fn (ShiftAssignment $assignment): bool => $date->betweenIncluded(
                $assignment->start_date,
                $assignment->end_date ?? $date->copy()->addYears(60),
            ))
            ->sortByDesc('start_date')
            ->first();

        return $assignment?->shift?->days
            ->firstWhere('weekday', $weekday);
    }

    /**
     * The shift's worked duration in seconds: ordinary journey minus lunch.
     */
    private function shiftDuration(ShiftDay $shiftDay): int
    {
        return $this->diffSeconds($shiftDay->start_time, $shiftDay->end_time)
            - $this->diffSeconds($shiftDay->lunch_start_time, $shiftDay->lunch_end_time);
    }

    /**
     * Seconds between two clock times, or 0 when either is missing.
     */
    private function diffSeconds(?CarbonInterface $start, ?CarbonInterface $end): int
    {
        if ($start === null || $end === null) {
            return 0;
        }

        return (int) $start->diffInSeconds($end);
    }

    /**
     * Project a mark's clock time onto the shift-time's date so the two can be
     * compared regardless of the calendar day each Carbon carries.
     */
    private function atTime(CarbonInterface $reference, CarbonInterface $mark): CarbonInterface
    {
        return $reference->copy()->setTime(
            (int) $mark->format('H'),
            (int) $mark->format('i'),
            (int) $mark->format('s'),
        );
    }

    /**
     * Format a clock time as hh:mm:ss (Art. 27 b), or null when absent.
     */
    private function time(?CarbonInterface $time): ?string
    {
        return $time?->format('H:i:s');
    }

    /**
     * Format seconds as a signed hh:mm:ss interval: "+" when positive, "-" when
     * negative and an unsigned "00:00:00" when zero (Art. 27 b.7, b.8, b.12).
     */
    private function signedInterval(int $seconds): string
    {
        if ($seconds === 0) {
            return '00:00:00';
        }

        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);

        return sprintf('%s%02d:%02d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Map user id to `Y-m-d` day => first IN / last OUT mark of that day.
     *
     * @param  list<int>  $userIds
     * @return array<int|string, array<string, array{in: Carbon|null, out: Carbon|null}>>
     */
    private function marksByUser(array $userIds, Carbon $start, Carbon $end): array
    {
        $byUser = Mark::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('date_time')
            ->get(['user_id', 'date_time', 'type'])
            ->groupBy('user_id');

        $result = [];

        foreach ($byUser as $userId => $marks) {
            $days = [];

            foreach ($marks as $mark) {
                $day = $mark->date_time->format('Y-m-d');

                if (! isset($days[$day])) {
                    $days[$day] = ['in' => null, 'out' => null];
                }

                // Marks are ordered ascending: keep the first IN and the last OUT.
                if ($mark->type === MarkType::In && $days[$day]['in'] === null) {
                    $days[$day]['in'] = $mark->date_time;
                } elseif ($mark->type === MarkType::Out) {
                    $days[$day]['out'] = $mark->date_time;
                }
            }

            $result[$userId] = $days;
        }

        return $result;
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int|string, EloquentCollection<int, Leave>>
     */
    private function approvedLeavesByUser(array $userIds, Carbon $start, Carbon $end): Collection
    {
        return Leave::query()
            ->whereIn('user_id', $userIds)
            ->where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get()
            ->groupBy('user_id');
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int|string, EloquentCollection<int, ShiftAssignment>>
     */
    private function shiftAssignmentsByUser(array $userIds, Carbon $start, Carbon $end): Collection
    {
        return ShiftAssignment::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('start_date', '<=', $end)
            ->where(fn ($query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $start))
            ->with('shift.days')
            ->get()
            ->groupBy('user_id');
    }

    /**
     * @return Collection<string, Holiday>
     */
    private function holidaysByDate(Carbon $start, Carbon $end): Collection
    {
        return Holiday::query()
            ->whereBetween('date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->format('Y-m-d'));
    }

    /**
     * Join a name and RUT as "Name - 12.345.678-5", dropping a missing RUT.
     */
    private function label(string $name, ?string $rut): string
    {
        return $rut === null ? $name : "{$name} - {$rut}";
    }
}
