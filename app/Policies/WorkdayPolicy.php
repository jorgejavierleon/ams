<?php

declare(strict_types=1);

namespace App\Policies;

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

    public function view(AuthUser $authUser, Workday $workday): bool
    {
        return $authUser->can('View:Workday');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Workday');
    }

    public function update(AuthUser $authUser, Workday $workday): bool
    {
        return $authUser->can('Update:Workday');
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
}
