<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Marks a model as owned by an organization (tenant).
 *
 * Applies {@see OrganizationScope} so reads are constrained to the current
 * organization, and stamps `organization_id` on creation when a tenant is active.
 */
trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute('organization_id', static::currentOrganizationId());
            }
        });
    }

    /**
     * Resolve the organization the current request/session is scoped to.
     *
     * Prefers the DT audit session organization (set by the inspector's
     * organization selector), then an explicit tenant-switcher override, and
     * finally falls back to the authenticated user's organization.
     */
    public static function currentOrganizationId(): ?int
    {
        $dtOrganizationId = session('dt_organization_id');

        if ($dtOrganizationId !== null) {
            return (int) $dtOrganizationId;
        }

        $sessionOrganizationId = session('organization_id');

        if ($sessionOrganizationId !== null) {
            return (int) $sessionOrganizationId;
        }

        return Auth::user()?->organization_id;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
