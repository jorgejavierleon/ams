<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Leave;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LeavePolicy
{
    use HandlesAuthorization;

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
