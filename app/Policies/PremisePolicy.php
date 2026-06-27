<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Premise;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PremisePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Premise');
    }

    public function view(AuthUser $authUser, Premise $premise): bool
    {
        return $authUser->can('View:Premise');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Premise');
    }

    public function update(AuthUser $authUser, Premise $premise): bool
    {
        return $authUser->can('Update:Premise');
    }

    public function delete(AuthUser $authUser, Premise $premise): bool
    {
        return $authUser->can('Delete:Premise');
    }

    public function restore(AuthUser $authUser, Premise $premise): bool
    {
        return $authUser->can('Restore:Premise');
    }

    public function forceDelete(AuthUser $authUser, Premise $premise): bool
    {
        return $authUser->can('ForceDelete:Premise');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Premise');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Premise');
    }

    public function replicate(AuthUser $authUser, Premise $premise): bool
    {
        return $authUser->can('Replicate:Premise');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Premise');
    }
}
