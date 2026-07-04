<?php

namespace App\Models\Scopes;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query for a tenant-owned model to the current organization.
 *
 * The scope is a no-op when no organization can be resolved (e.g. unauthenticated
 * requests or console commands), which keeps seeders and system tasks unscoped.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var BelongsToOrganization $model */
        $organizationId = $model::currentOrganizationId();

        if ($organizationId !== null) {
            $builder->where($model->qualifyColumn('organization_id'), $organizationId);
        }
    }
}
