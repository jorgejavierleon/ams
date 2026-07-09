<?php

namespace App\Models\Scopes;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Constrains holiday reads to the official (global) list plus the current
 * organization's own holidays.
 *
 * Unlike {@see OrganizationScope}, official holidays (`organization_id = NULL`)
 * remain visible to every tenant. The scope is a no-op when no organization can
 * be resolved (console commands, SaaS panel), which lets the sync command and
 * super-admin see and manage the full table.
 */
class HolidayScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = static::currentOrganizationId();

        if ($organizationId !== null) {
            $column = $model->qualifyColumn('organization_id');

            $builder->where(function (Builder $query) use ($column, $organizationId): void {
                $query->whereNull($column)
                    ->orWhere($column, $organizationId);
            });
        }
    }

    /**
     * Resolve the organization the current request/session is scoped to.
     *
     * Mirrors {@see BelongsToOrganization::currentOrganizationId()}:
     * prefers the tenant-switcher session override, then the authenticated user.
     */
    public static function currentOrganizationId(): ?int
    {
        $sessionOrganizationId = session('organization_id');

        if ($sessionOrganizationId !== null) {
            return (int) $sessionOrganizationId;
        }

        return Auth::user()?->organization_id;
    }
}
