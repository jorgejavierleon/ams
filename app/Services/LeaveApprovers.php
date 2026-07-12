<?php

namespace App\Services;

use App\Models\Leave;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves who should be notified about a leave request. The direct supervisor
 * is the primary approver when their role currently holds `ApproveTeam:Leave`;
 * otherwise approval falls back to the organization's admins. Admins are always
 * copied on submissions for visibility.
 */
class LeaveApprovers
{
    /**
     * The primary approver(s) for a leave: the requester's supervisor when they
     * may approve, otherwise the organization admins.
     *
     * @return Collection<int, User>
     */
    public function primary(Leave $leave): Collection
    {
        $supervisor = $leave->user->supervisor;

        if ($supervisor && $supervisor->can('ApproveTeam:Leave')) {
            return collect([$supervisor]);
        }

        return $this->admins($leave);
    }

    /**
     * The admins of the leave's organization.
     *
     * @return Collection<int, User>
     */
    public function admins(Leave $leave): Collection
    {
        return User::query()
            ->role('admin')
            ->where('organization_id', $leave->organization_id)
            ->get();
    }

    /**
     * Recipients for a submitted-request notification: the primary approver(s)
     * plus the organization admins (CC), de-duplicated.
     *
     * @return Collection<int, User>
     */
    public function submissionRecipients(Leave $leave): Collection
    {
        return $this->primary($leave)
            ->merge($this->admins($leave))
            ->unique('id')
            ->values();
    }
}
