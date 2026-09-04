<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Constrains every query for a user-owned model to the current user (KOL-105):
 * route-model-binding 404s a request for another user's row automatically,
 * the same way {@see OrganizationScope} 404s a cross-org request, rather
 * than needing each controller/route to check ownership itself.
 *
 * The scope is a no-op when no user is authenticated (e.g. console commands
 * or queued jobs), which keeps seeders and system tasks unscoped.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class UserScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $userId = Auth::id();

        if ($userId !== null) {
            $builder->where($model->qualifyColumn('user_id'), $userId);
        }
    }
}
