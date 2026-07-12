<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Leave;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LeavePolicy
{
    use HandlesAuthorization;

    /**
     * View the team leaves index. Admins reach this via the super-admin gate;
     * a supervisor with the `ViewTeam:Leave` permission sees their direct
     * reports' requests.
     */
    public function viewTeam(User $user): bool
    {
        return $user->can('ViewTeam:Leave');
    }

    /**
     * Approve a leave. Admins may approve any request via the super-admin gate;
     * a supervisor may approve only their own team's requests, and only while
     * the `ApproveTeam:Leave` permission is granted to their role.
     */
    public function approve(User $user, Leave $leave): bool
    {
        return $this->canDecide($user, $leave);
    }

    /**
     * Reject a leave — same authority rules as {@see approve()}.
     */
    public function reject(User $user, Leave $leave): bool
    {
        return $this->canDecide($user, $leave);
    }

    /**
     * Shared authority check for approve/reject: the requester's direct
     * supervisor holding the team-approval permission. Admins are handled
     * earlier by the super-admin gate.
     */
    private function canDecide(User $user, Leave $leave): bool
    {
        return $user->can('ApproveTeam:Leave')
            && $leave->user->supervisor_id === $user->id;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Leave');
    }

    public function view(AuthUser $authUser, Leave $leave): bool
    {
        return $authUser->can('View:Leave');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Leave');
    }

    public function update(AuthUser $authUser, Leave $leave): bool
    {
        return $authUser->can('Update:Leave');
    }

    public function delete(AuthUser $authUser, Leave $leave): bool
    {
        return $authUser->can('Delete:Leave');
    }

    public function restore(AuthUser $authUser, Leave $leave): bool
    {
        return $authUser->can('Restore:Leave');
    }

    public function forceDelete(AuthUser $authUser, Leave $leave): bool
    {
        return $authUser->can('ForceDelete:Leave');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Leave');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Leave');
    }

    public function replicate(AuthUser $authUser, Leave $leave): bool
    {
        return $authUser->can('Replicate:Leave');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Leave');
    }
}
