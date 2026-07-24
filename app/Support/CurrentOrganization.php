<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Resolves the organization (tenant) the current request or session is scoped
 * to, independent of any particular model.
 */
class CurrentOrganization
{
    /**
     * Prefer the DT audit session organization (set by the inspector's
     * organization selector), then an explicit tenant-switcher override, and
     * finally the authenticated user's organization.
     */
    public static function id(): ?int
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
}
