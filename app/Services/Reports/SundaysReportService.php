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
 * Builds the "Reporte de días domingo y/o días festivos" required by
 * Resolución 38, Art. 27 c): for each selected worker, every Sunday and public
 * holiday in the range on which the worker actually worked or was rostered to
 * work — never the Sundays/holidays their ordinary journey leaves them free.
 *
 * The article prescribes the exact columns and nothing else ("sólo las columnas
 * con la información que se indica"): a first column flagging retail workers
 * entitled to additional Sunday rest (c.2); the date (c.3); "Asistencia" yes/no
 * (c.4); "Ausencia" justified/unjustified (c.5); "Observaciones" (c.6); a
 * per-month subtotal line of Sundays/holidays worked (c.7); and a final total
 * line (c.8). Rows are grouped by month so those subtotal lines can be
 * interleaved.
 *
 * A worker whose journey never falls on a Sunday or holiday (and who has no
 * marks on any such day) yields no months and carries the legend the article
 * requires: "La jornada de este trabajador no incluye domingos o festivos".
 *
 * Each block carries the header data the article demands: employer (razón
 * social + RUT), worker (name + RUT), place of service (premise) and the
 * position that entitles the worker to work on such days (c.1).
 */
class SundaysReportService
{
    /**
     * @param  list<int>  $userIds
     * @return list<array{
     *     employee: string,
     *     employer: string|null,
     *     premise: string|null,
     *     position: string|null,
     *     additionalSundays: bool,
     *     months: list<array{key: string, label: string, worked: int, rows: list<array{
     *         date: string,
     *         dayType: 'sunday'|'holiday',
     *         holiday: string|null,
     *         attendance: bool,
     *         absence: 'justified'|'unjustified'|null,
     *         observation: array{kind: string, name?: string, type?: string}|null,
     *     }>}>,
     *     total: int,
     *     emptyReason: 'no-sundays'|null,
     * }>
     */
    public function build(Carbon $start, Carbon $end, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $userIds)
            ->with([
                'company:id,social_reason,rut',
                'premise:id,name',
                'position:id,name',
            ])
            ->orderBy('name')
            ->get();

        $markDates = $this->markDatesByUser($userIds, $start, $end);
        $leaves = $this->approvedLeavesByUser($userIds, $start, $end);
        $assignments = $this->shiftAssignmentsByUser($userIds, $start, $end);
        $holidays = $this->holidaysByDate($start, $end);

        // Only Sundays and holidays are relevant to this report (Art. 27 c).
        $relevantDates = collect(CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()))
            ->filter(fn (Carbon $date): bool => $date->isSunday() || $holidays->has($date->format('Y-m-d')))
            ->values();

        return $users->map(function (User $user) use ($relevantDates, $markDates, $leaves, $assignments, $holidays): array {
            $userLeaves = $leaves->get($user->id, collect());
            $userAssignments = $assignments->get($user->id, collect());
            $attendedDates = $markDates->get($user->id, collect());

            $months = $this->monthsFor($relevantDates, $attendedDates, $userAssignments, $userLeaves, $holidays);
            $total = $months->sum('worked');

            return [
                'employee' => $this->label($user->name, $user->formatted_rut ?? $user->rut),
                'employer' => $user->company === null
                    ? null
                    : $this->label($user->company->social_reason, $user->company->formatted_rut ?? $user->company->rut),
                'premise' => $user->premise?->name,
                'position' => $user->position?->name,
                'additionalSundays' => $user->has_additional_sundays,
                'months' => $months->values()->all(),
                'total' => $total,
                // The fixed-journey legend (Art. 27 c, final paragraph).
                'emptyReason' => $months->isEmpty() ? 'no-sundays' : null,
            ];
        })->all();
    }

    /**
     * Group the worker's relevant days into month blocks, each with its rows and
     * a subtotal of Sundays/holidays worked. A day is only included when the
     * worker punched a mark ("laboró") or was rostered that day ("debió
     * hacerlo"); days off are omitted entirely.
     *
     * @param  Collection<int, Carbon>  $relevantDates
     * @param  Collection<string, true>  $attendedDates
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @param  Collection<int, Leave>  $leaves
     * @param  Collection<string, Holiday>  $holidays
     * @return Collection<int, array{key: string, label: string, worked: int, rows: list<array<string, mixed>>}>
     */
    private function monthsFor(
        Collection $relevantDates,
        Collection $attendedDates,
        Collection $assignments,
        Collection $leaves,
        Collection $holidays,
    ): Collection {
        return $relevantDates
            ->map(function (Carbon $date) use ($attendedDates, $assignments, $leaves, $holidays): ?array {
                $key = $date->format('Y-m-d');
                $attended = $attendedDates->has($key);
                $shiftDay = $this->shiftDayFor($assignments, $date);
                $scheduled = $shiftDay !== null && ! $shiftDay->is_free;

                // Show the day only if worked or rostered (Art. 27 c, final paragraph).
                if (! $attended && ! $scheduled) {
                    return null;
                }

                $holiday = $holidays->get($key);
                $leave = $leaves->first(
                    fn (Leave $leave): bool => $date->betweenIncluded($leave->start_date, $leave->end_date)
                );

                return [
                    'monthKey' => $date->format('Y-m'),
                    // Prefer the month name for the subtotal label (Art. 27 c.7).
                    'monthLabel' => $date->translatedFormat('F Y'),
                    'row' => [
                        // dd/mm/aa per Resolución 38, Art. 27 c.3.
                        'date' => $date->format('d/m/y'),
                        'dayType' => $holiday !== null ? 'holiday' : 'sunday',
                        'holiday' => $holiday?->name,
                        'attendance' => $attended,
                        'absence' => $this->absence($attended, $leave),
                        'observation' => $this->observation($holiday, $leave),
                    ],
                ];
            })
            ->filter()
            ->groupBy('monthKey')
            ->map(fn (Collection $entries, string $monthKey): array => [
                'key' => $monthKey,
                'label' => $entries->first()['monthLabel'],
                'worked' => $entries->filter(fn (array $entry): bool => $entry['row']['attendance'])->count(),
                'rows' => $entries->pluck('row')->all(),
            ])
            ->values();
    }

    /**
     * The "Ausencia" column (Art. 27 c.5): null when the worker attended,
     * otherwise justified when an approved leave covers the day, else
     * unjustified.
     */
    private function absence(bool $attended, ?Leave $leave): ?string
    {
        if ($attended) {
            return null;
        }

        return $leave !== null ? 'justified' : 'unjustified';
    }

    /**
     * The "Observaciones" column (Art. 27 c.6): the leave type covering the
     * absence, or the holiday name identifying the festivo, in that order.
     *
     * @return array{kind: string, name?: string, type?: string}|null
     */
    private function observation(?Holiday $holiday, ?Leave $leave): ?array
    {
        if ($leave !== null) {
            return ['kind' => 'leave', 'type' => $leave->type->value];
        }

        if ($holiday !== null) {
            return ['kind' => 'holiday', 'name' => $holiday->name];
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
