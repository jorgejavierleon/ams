<?php

namespace App\Managers;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use App\Notifications\LeaveApproved;
use App\Notifications\LeaveRejected;
use App\Observers\LeaveObserver;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Encapsulates the approve/reject transitions for a leave request, keeping the
 * employee's vacation balance in sync. Status changes flow through the model so
 * the {@see LeaveObserver} still fires workday recalculation.
 */
class LeaveManager
{
    /**
     * @throws Throwable
     */
    public function approve(Leave $leave): void
    {
        if ($leave->type === LeaveType::Vacation) {
            DB::transaction(fn () => $this->approveVacation($leave));
        } else {
            DB::transaction(fn () => $this->approveGeneralLeave($leave));
        }

        $leave->user->notify(new LeaveApproved($leave));
    }

    /**
     * @throws Throwable
     */
    public function reject(Leave $leave): void
    {
        if ($leave->type === LeaveType::Vacation) {
            DB::transaction(fn () => $this->rejectVacation($leave));
        } else {
            DB::transaction(fn () => $this->rejectGeneralLeave($leave));
        }

        $leave->user->notify(new LeaveRejected($leave));
    }

    /**
     * Permanently remove a leave, refunding the vacation balance when an
     * already-approved vacation is deleted. Deletion flows through the model so
     * the {@see LeaveObserver} still fires workday recalculation.
     *
     * @throws Throwable
     */
    public function delete(Leave $leave): void
    {
        DB::transaction(function () use ($leave): void {
            if ($leave->type === LeaveType::Vacation && $leave->status === LeaveStatus::Approved) {
                $leave->user->vacation_days += $leave->business_days_requested;
                $leave->user->save();
            }

            $leave->delete();
        });
    }

    private function approveGeneralLeave(Leave $leave): void
    {
        $leave->status = LeaveStatus::Approved;
        $leave->approved_by = auth()->id();
        $leave->save();
    }

    private function rejectGeneralLeave(Leave $leave): void
    {
        $leave->status = LeaveStatus::Rejected;
        $leave->approved_by = null;
        $leave->save();
    }

    private function approveVacation(Leave $leave): void
    {
        $leave->status = LeaveStatus::Approved;
        $leave->approved_by = auth()->id();
        $leave->save();

        $leave->user->vacation_days -= $leave->business_days_requested;
        $leave->user->save();
    }

    private function rejectVacation(Leave $leave): void
    {
        // Only refund the balance if the vacation had previously been approved.
        if ($leave->status === LeaveStatus::Approved) {
            $leave->user->vacation_days += $leave->business_days_requested;
            $leave->user->save();
        }

        $leave->status = LeaveStatus::Rejected;
        $leave->approved_by = null;
        $leave->save();
    }
}
