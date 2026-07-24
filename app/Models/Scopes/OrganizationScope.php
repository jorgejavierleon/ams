<?php

namespace App\Models\Scopes;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query for a tenant-owned model to the current organization.
 *
 * The scope is a no-op when no organization can be resolved (e.g. unauthenticated
 * requests or console commands), which keeps seeders and system tasks unscoped.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class OrganizationScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = CurrentOrganization::id();

        if ($organizationId !== null) {
            $builder->where($model->qualifyColumn('organization_id'), $organizationId);
        }
    }
}
