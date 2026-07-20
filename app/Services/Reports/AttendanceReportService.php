<?php

namespace App\Services\Reports;

use App\Enums\LeaveStatus;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Mark;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the "Reporte de asistencia" required by Resolución 38, Art. 27 a):
 * a day-by-day attendance grid for each selected worker over the chosen date
 * range. For every day it resolves whether the worker attended (any mark),
 * whether an absence was justified (approved leave, free shift day or holiday)
 * or unjustified, and a human observation (Libre / Feriado / leave type).
 *
 * The report is grouped per worker and each block carries the header data the
 * article demands: employer (razón social + RUT), worker (name + RUT) and the
 * place of service (premise).
 */
class AttendanceReportService
{
    /**
     * @param  list<int>  $userIds
     * @return list<array{
     *     employee: string,
     *     employer: string|null,
     *     premise: string|null,
     *     rows: list<array{
     *         date: string,
     *         attendance: bool,
     *         absence: 'justified'|'unjustified'|null,
     *         observation: array{kind: string, name?: string, type?: string}|null,
     *     }>,
     * }>
     */
    public function build(Carbon $start, Carbon $end, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $userIds)
            ->with(['company:id,social_reason,rut', 'premise:id,name'])
            ->orderBy('name')
            ->get();

        $markDates = $this->markDatesByUser($userIds, $start, $end);
        $leaves = $this->approvedLeavesByUser($userIds, $start, $end);
        $assignments = $this->shiftAssignmentsByUser($userIds, $start, $end);
        $holidays = $this->holidaysByDate($start, $end);

        $dates = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay());

        return $users->map(function (User $user) use ($dates, $markDates, $leaves, $assignments, $holidays): array {
            $userLeaves = $leaves->get($user->id, collect());
            $userAssignments = $assignments->get($user->id, collect());
            $attendedDates = $markDates->get($user->id, collect());

            $rows = [];

            foreach ($dates as $date) {
                $key = $date->format('Y-m-d');
                $attended = $attendedDates->has($key);
                $shiftDay = $this->shiftDayFor($userAssignments, $date);
                $isFree = (bool) ($shiftDay?->is_free);
                $holiday = $holidays->get($key);
                $leave = $userLeaves->first(
                    fn (Leave $leave): bool => $date->betweenIncluded($leave->start_date, $leave->end_date)
                );

                $rows[] = [
                    // dd/mm/aa per Resolución 38, Art. 27 a.2.
                    'date' => $date->format('d/m/y'),
                    'attendance' => $attended,
                    'absence' => $this->absence($attended, $leave, $isFree, $holiday),
                    'observation' => $this->observation($isFree, $holiday, $leave),
                ];
            }

            return [
                'employee' => $this->label($user->name, $user->formatted_rut ?? $user->rut),
                'employer' => $user->company === null
                    ? null
                    : $this->label($user->company->social_reason, $user->company->formatted_rut ?? $user->company->rut),
                'premise' => $user->premise?->name,
                'rows' => $rows,
            ];
        })->all();
    }

    /**
     * The "Ausencia" column: null when the worker attended, otherwise justified
     * (approved leave, free shift day or holiday) or unjustified.
     */
    private function absence(bool $attended, ?Leave $leave, bool $isFree, ?Holiday $holiday): ?string
    {
        if ($attended) {
            return null;
        }

        if ($leave !== null || $isFree || $holiday !== null) {
            return 'justified';
        }

        return 'unjustified';
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
     * Resolve the shift day governing a date: the most recently started
     * assignment active on that date, matched to the weekday's shift day
     * (MySQL WEEKDAY convention: Monday = 0 … Sunday = 6).
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
     * Map user id to the set of `Y-m-d` days on which the worker has any mark.
     *
     * @param  list<int>  $userIds
     * @return Collection<int, Collection<string, true>>
     */
    private function markDatesByUser(array $userIds, Carbon $start, Carbon $end): Collection
    {
        return Mark::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get(['user_id', 'date_time'])
            ->groupBy('user_id')
            ->map(fn (Collection $marks): Collection => $marks
                ->keyBy(fn (Mark $mark): string => $mark->date_time->format('Y-m-d'))
                ->map(fn (): bool => true));
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, Collection<int, Leave>>
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
     * @return Collection<int, Collection<int, ShiftAssignment>>
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
