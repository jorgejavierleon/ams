<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Counts the business (working) days an employee would be absent over a leave's
 * date range, so the vacation deduction can be pre-filled instead of typed.
 *
 * A day counts when the employee's active shift assignment schedules work on
 * that weekday (a non-free {@see ShiftDay}) and the day is not a holiday —
 * unless that shift is flagged to work on holidays. Days the range covers with
 * no shift assignment fall back to a Monday–Friday work week.
 */
class BusinessDaysCalculator
{
    public function calculate(User $employee, Carbon $start, Carbon $end): float
    {
        if ($end->lt($start)) {
            return 0.0;
        }

        $holidays = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $assignments = $employee->shiftAssignments()
            ->where('start_date', '<=', $end->toDateString())
            ->where(function ($query) use ($start): void {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $start->toDateString());
            })
            ->with('shift.days')
            ->get();

        $count = 0.0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($this->isWorkingDay($date, $assignments, $holidays->has($date->toDateString()))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     */
    private function isWorkingDay(Carbon $date, $assignments, bool $isHoliday): bool
    {
        // SQL WEEKDAY format used by shift_days: 0 = Monday ... 6 = Sunday.
        $weekday = $date->dayOfWeekIso - 1;

        $assignment = $assignments->first(function (ShiftAssignment $assignment) use ($date): bool {
            return $assignment->start_date->lte($date)
                && ($assignment->end_date === null || $assignment->end_date->gte($date));
        });

        if ($assignment === null || $assignment->shift === null) {
            // No shift scheduled for this day: assume a Monday–Friday work week.
            return $weekday <= 4 && ! $isHoliday;
        }

        $shift = $assignment->shift;

        $shiftDay = $shift->days->first(
            fn (ShiftDay $day): bool => $day->weekday === $weekday && $day->date === null,
        );

        if ($shiftDay === null || $shiftDay->is_free) {
            return false;
        }

        return ! $isHoliday || $shift->work_on_holidays;
    }
}
