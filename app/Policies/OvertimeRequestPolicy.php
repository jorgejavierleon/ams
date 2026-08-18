<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Mirrors {@see OvertimeAuthorizationPolicy}'s decide-authority shape for the
 * employee's own request (KOL-45).
 */
class OvertimeRequestPolicy
{
    use HandlesAuthorization;

    /**
     * An employee may only request overtime for themselves.
     */
    public function create(User $user): bool
    {
        return $user->can('RequestOwn:OvertimeAuthorization');
    }

    /**
     * View the standalone requests screen (KOL-72). Admins reach this via the
     * super-admin gate; a supervisor with `ViewTeam:OvertimeAuthorization`
     * sees their direct reports' requests, and whoever holds
     * `Manage:OvertimeAuthorization` sees every team's, mirroring
     * {@see OvertimeAuthorizationPolicy::viewTeam()}.
     */
    public function viewTeam(User $user): bool
    {
        return $user->can('ViewTeam:OvertimeAuthorization') || $user->can('Manage:OvertimeAuthorization');
    }

    /**
     * Approve a request. Admins may approve any record via the super-admin
     * gate; a supervisor may approve only their own team's records, and only
     * while `ApproveTeam:OvertimeAuthorization` is granted to their role.
     */
    public function approve(User $user, OvertimeRequest $overtimeRequest): bool
    {
        return $this->canDecide($user, $overtimeRequest);
    }

    /**
     * Reject a request — same authority rules as {@see approve()}.
     */
    public function reject(User $user, OvertimeRequest $overtimeRequest): bool
    {
        return $this->canDecide($user, $overtimeRequest);
    }

    private function canDecide(User $user, OvertimeRequest $overtimeRequest): bool
    {
        return $user->can('ApproveTeam:OvertimeAuthorization')
            && $overtimeRequest->user->supervisor_id === $user->id;
    }
}
