<?php

namespace App\Observers;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Events\WorkdaysRecalculationNeeded;
use App\Models\Leave;

class LeaveObserver
{
    /**
     * Stamp the creating user when not already set.
     */
    public function creating(Leave $leave): void
    {
        if ($leave->getAttribute('created_by') === null && ($id = auth()->id()) !== null) {
            $leave->created_by = (int) $id;
        }
    }

    /**
     * Medical leaves are automatically approved on save.
     */
    public function saving(Leave $leave): void
    {
        if ($leave->type === LeaveType::Medical) {
            $leave->status = LeaveStatus::Approved;
        }
    }

    /**
     * Handle the Leave "created" event.
     */
    public function created(Leave $leave): void
    {
        if ($leave->status === LeaveStatus::Approved) {
            $this->recalculateWorkdays($leave);
        }
    }

    /**
     * Handle the Leave "updated" event.
     */
    public function updated(Leave $leave): void
    {
        if (
            $leave->wasChanged('status')
            || $leave->wasChanged('start_date')
            || $leave->wasChanged('end_date')
        ) {
            $this->recalculateWorkdays($leave);
        }
    }

    /**
     * Handle the Leave "deleted" event.
     */
    public function deleted(Leave $leave): void
    {
        if ($leave->status === LeaveStatus::Approved) {
            $this->recalculateWorkdays($leave);
        }
    }

    /**
     * Dispatch a recalculation for the leave's employee over its date range.
     */
    private function recalculateWorkdays(Leave $leave): void
    {
        WorkdaysRecalculationNeeded::dispatch(
            collect([$leave->user_id]),
            $leave->start_date,
            $leave->end_date,
        );
    }
}
