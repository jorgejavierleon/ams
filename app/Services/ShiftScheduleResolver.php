<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use App\Support\ScheduledDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Expands a user's shift assignment across a date range, day by day, for the
 * Jornada tab's Próximos screen. `MarkManager::getShiftForDate` resolves one
 * date at a time and that stays exactly as it is; this exists because nothing
 * before it expanded a range, and it is modelled on
 * {@see BusinessDaysCalculator}'s own shape — two bounded queries up front,
 * everything else done in PHP so the cost does not grow with how many days are
 * asked for.
 *
 * A date with no active assignment, no shift scheduled for its weekday, or a
 * free {@see ShiftDay} is left out of the result entirely — there is nothing
 * ordinary to report about it. A date an approved {@see Leave} or a
 * {@see Holiday} the shift does not work covers is kept, with its schedule
 * fields replaced by that fact instead.
 */
class ShiftScheduleResolver
{
    /**
     * @return Collection<int, ScheduledDay>
     */
    public function resolve(User $user, Carbon $start, Carbon $end): Collection
    {
        // Calendar dates throughout, not wall-clock moments: `$start`/`$end`
        // arrive carrying the caller's current time-of-day (both TodayController
        // and this controller build them off `Carbon::now()`), while
        // `Leave.start_date`/`end_date` and a Holiday's `date` are cast to
        // midnight. Comparing a leave's own last day against "today at 14:23"
        // reads it as already over; stripping the time here is what makes every
        // date comparison below a same-day match instead of missing by hours.
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        $assignments = $user->shiftAssignments()
            ->where('start_date', '<=', $end->toDateString())
            ->where(function ($query) use ($start): void {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $start->toDateString());
            })
            ->with('shift.days')
            ->get();

        $leaves = Leave::query()
            ->where('user_id', $user->id)
            ->where('status', LeaveStatus::Approved)
            ->whereDate('end_date', '>=', $start->toDateString())
            ->whereDate('start_date', '<=', $end->toDateString())
            ->get();

        $holidays = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $premise = $user->premise?->name;

        $days = collect();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = $this->resolveDate($date->copy(), $assignments, $leaves, $holidays, $premise);

            if ($day !== null) {
                $days->push($day);
            }
        }

        return $days;
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @param  Collection<int, Leave>  $leaves
     * @param  Collection<string, Holiday>  $holidays
     */
    private function resolveDate(
        Carbon $date,
        Collection $assignments,
        Collection $leaves,
        Collection $holidays,
        ?string $premise,
    ): ?ScheduledDay {
        // ShiftDay weekdays are 0=Monday … 6=Sunday, matching
        // MarkManager::getShiftDayForAssignment's own reading of dayOfWeekIso.
        $weekday = $date->dayOfWeekIso - 1;

        // String comparison throughout this method rather than Carbon's own
        // lte/gte: `$date` carries the employee's timezone (it descends from
        // TimeZoneService::getUserTimezone in the controller), while
        // ShiftAssignment/Leave's date columns cast to midnight in the app's
        // default timezone. Carbon compares the underlying instant, not the
        // calendar label, so "medianoche en Santiago" and "midnight UTC" on the
        // same nominal date are two different instants and lte/gte silently
        // disagree about which came first. A calendar date has no instant to
        // compare — only the label does.
        $dateString = $date->toDateString();

        $assignment = $assignments->first(
            fn (ShiftAssignment $assignment): bool => $assignment->start_date->toDateString() <= $dateString
                && ($assignment->end_date === null || $assignment->end_date->toDateString() >= $dateString),
        );

        if ($assignment === null || $assignment->shift === null) {
            return null;
        }

        $shift = $assignment->shift;

        $shiftDay = $shift->days->first(
            fn (ShiftDay $day): bool => $day->weekday === $weekday && $day->date === null,
        );

        if ($shiftDay === null || $shiftDay->is_free) {
            return null;
        }

        $leave = $leaves->first(
            fn (Leave $leave): bool => $leave->start_date->toDateString() <= $dateString
                && $leave->end_date->toDateString() >= $dateString,
        );

        $holiday = $holidays->get($dateString);
        $isBlockedHoliday = $holiday !== null && ! $shift->work_on_holidays;

        $annotated = $leave !== null || $isBlockedHoliday;
        $hasLunch = $shiftDay->lunch_start_time !== null && $shiftDay->lunch_end_time !== null;

        return new ScheduledDay(
            date: $date,
            premise: $premise,
            startTime: $annotated ? null : $shiftDay->start_time,
            endTime: $annotated ? null : $shiftDay->end_time,
            lunchStartTime: $annotated || ! $hasLunch ? null : $shiftDay->lunch_start_time,
            lunchEndTime: $annotated || ! $hasLunch ? null : $shiftDay->lunch_end_time,
            // Leave takes priority when both land on the same date — it is the
            // more specific, personal fact, and the mobile row has one trailing
            // slot to put it in.
            leaveTypeLabel: $leave?->type->label(),
            holidayName: $leave === null && $isBlockedHoliday ? $holiday->name : null,
        );
    }
}
