<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MarkPolicy
{
    use HandlesAuthorization;

    /**
     * Register an attendance punch for oneself. Admins reach this via the
     * super-admin gate; employees hold the `ClockOwn:Mark` permission.
     */
    public function clockOwn(User $user): bool
    {
        return $user->can('ClockOwn:Mark');
    }

    /**
     * View one's own attendance marks.
     */
    public function viewOwn(User $user): bool
    {
        return $user->can('ViewOwn:Mark');
    }
}
