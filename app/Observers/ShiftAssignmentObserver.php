<?php

namespace App\Observers;

use App\Events\WorkdaysRecalculationNeeded;
use App\Models\ShiftAssignment;

class ShiftAssignmentObserver
{
    /**
     * Handle the ShiftAssignment "created" event.
     */
    public function created(ShiftAssignment $shiftAssignment): void
    {
        $this->recalculateWorkdays($shiftAssignment);
    }

    /**
     * Handle the ShiftAssignment "updated" event.
     */
    public function updated(ShiftAssignment $shiftAssignment): void
    {
        if ($shiftAssignment->wasChanged('start_date') || $shiftAssignment->wasChanged('end_date')) {
            $this->recalculateWorkdays($shiftAssignment);
        }
    }

    /**
     * Handle the ShiftAssignment "deleted" event.
     */
    public function deleted(ShiftAssignment $shiftAssignment): void
    {
        $this->recalculateWorkdays($shiftAssignment);
    }

    /**
     * Dispatch a recalculation for the assignment's employee over the affected
     * range. Assignments that start in the future have no past workdays to
     * touch, and an open (or future) end date is clamped to today.
     */
    private function recalculateWorkdays(ShiftAssignment $shiftAssignment): void
    {
        if ($shiftAssignment->start_date->gte(now())) {
            return;
        }

        $endDate = $shiftAssignment->end_date;

        if ($endDate === null || $endDate->gt(now())) {
            $endDate = now();
        }

        WorkdaysRecalculationNeeded::dispatch(
            collect([$shiftAssignment->user_id]),
            $shiftAssignment->start_date,
            $endDate,
        );
    }
}
