<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OvertimeAuthorization;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OvertimeAuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * View the team overtime queue. Admins reach this via the super-admin gate;
     * a supervisor with the `ViewTeam:OvertimeAuthorization` permission sees
     * their direct reports' records.
     */
    public function viewTeam(User $user): bool
    {
        return $user->can('ViewTeam:OvertimeAuthorization');
    }

    /**
     * Approve a day's overtime. Admins may approve any record via the
     * super-admin gate; a supervisor may approve only their own team's
     * records, and only while the `ApproveTeam:OvertimeAuthorization`
     * permission is granted to their role.
     */
    public function approve(User $user, OvertimeAuthorization $authorization): bool
    {
        return $this->canDecide($user, $authorization);
    }

    /**
     * Object to a day's overtime — same authority rules as {@see approve()}.
     */
    public function object(User $user, OvertimeAuthorization $authorization): bool
    {
        return $this->canDecide($user, $authorization);
    }

    /**
     * Shared authority check for approve/object: the employee's direct
     * supervisor holding the team-approval permission. Admins are handled
     * earlier by the super-admin gate.
     */
    private function canDecide(User $user, OvertimeAuthorization $authorization): bool
    {
        return $user->can('ApproveTeam:OvertimeAuthorization')
            && $authorization->user->supervisor_id === $user->id;
    }
}
