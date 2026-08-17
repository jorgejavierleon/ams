<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workday;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WorkdayPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Workday');
    }

    /**
     * View the team Jornadas index. Admins reach this via the super-admin
     * gate; a supervisor with `ViewTeam:Workday` sees their direct reports
     * (KOL-71) — a separate permission domain from `OvertimeAuthorization`'s
     * own ViewTeam, since seeing a day's attendance and deciding its overtime
     * are different authorities that happen to be exercised from the same
     * screen now.
     */
    public function viewTeam(User $user): bool
    {
        return $user->can('ViewTeam:Workday');
    }

    /**
     * View a single workday's detail. General `View:Workday` or, for a
     * supervisor, the day belonging to one of their own direct reports.
     */
    public function view(User $authUser, Workday $workday): bool
    {
        return $authUser->can('View:Workday') || $this->onOwnTeam($authUser, $workday);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Workday');
    }

    /**
     * Act on a workday: open a mark-modification request, decide an
     * overtime day. General `Update:Workday` or, for a supervisor, the day
     * belonging to one of their own direct reports while they hold
     * `ApproveTeam:Workday`.
     */
    public function update(User $authUser, Workday $workday): bool
    {
        return $authUser->can('Update:Workday') || $this->onOwnTeam($authUser, $workday);
    }

    public function delete(AuthUser $authUser, Workday $workday): bool
    {
        return $authUser->can('Delete:Workday');
    }

    public function restore(AuthUser $authUser, Workday $workday): bool
    {
        return $authUser->can('Restore:Workday');
    }

    public function forceDelete(AuthUser $authUser, Workday $workday): bool
    {
        return $authUser->can('ForceDelete:Workday');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Workday');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Workday');
    }

    public function replicate(AuthUser $authUser, Workday $workday): bool
    {
        return $authUser->can('Replicate:Workday');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Workday');
    }

    /**
     * Shared team-authority check for {@see view()} and {@see update()}: the
     * employee's direct supervisor holding `ApproveTeam:Workday`. Mirrors
     * {@see LeavePolicy::canDecide()} and {@see OvertimeAuthorizationPolicy::canDecide()}.
     */
    private function onOwnTeam(User $user, Workday $workday): bool
    {
        return $user->can('ApproveTeam:Workday') && $workday->user->supervisor_id === $user->id;
    }
}
