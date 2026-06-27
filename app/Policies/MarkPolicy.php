<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mark;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MarkPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_mark');
    }

    public function view(User $user, Mark $mark): bool
    {
        return $user->can('view_mark');
    }

    public function create(User $user): bool
    {
        return $user->can('create_mark');
    }

    public function update(User $user, Mark $mark): bool
    {
        return $user->can('update_mark');
    }

    public function delete(User $user, Mark $mark): bool
    {
        return $user->can('delete_mark');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_mark');
    }

    public function forceDelete(User $user, Mark $mark): bool
    {
        return $user->can('force_delete_mark');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_mark');
    }

    public function restore(User $user, Mark $mark): bool
    {
        return $user->can('restore_mark');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_mark');
    }

    public function replicate(User $user, Mark $mark): bool
    {
        return $user->can('replicate_mark');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_mark');
    }
}
