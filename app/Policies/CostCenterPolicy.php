<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CostCenter;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CostCenterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CostCenter');
    }

    public function view(AuthUser $authUser, CostCenter $costCenter): bool
    {
        return $authUser->can('View:CostCenter');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CostCenter');
    }

    public function update(AuthUser $authUser, CostCenter $costCenter): bool
    {
        return $authUser->can('Update:CostCenter');
    }

    public function delete(AuthUser $authUser, CostCenter $costCenter): bool
    {
        return $authUser->can('Delete:CostCenter');
    }

    public function restore(AuthUser $authUser, CostCenter $costCenter): bool
    {
        return $authUser->can('Restore:CostCenter');
    }

    public function forceDelete(AuthUser $authUser, CostCenter $costCenter): bool
    {
        return $authUser->can('ForceDelete:CostCenter');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CostCenter');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CostCenter');
    }

    public function replicate(AuthUser $authUser, CostCenter $costCenter): bool
    {
        return $authUser->can('Replicate:CostCenter');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CostCenter');
    }
}
