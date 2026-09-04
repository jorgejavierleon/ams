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
     * A queued job (e.g. ProcessImportRun, KOL-102) has no HTTP session or
     * authenticated user to resolve a tenant from, but still needs every
     * org-scoped query it triggers indirectly to see the run's own
     * organization. {@see self::runAs()} sets this for the duration of such
     * a job; {@see self::id()} prefers it over session/auth when set.
     */
    private static ?int $override = null;

    /**
     * Prefer an explicit {@see self::runAs()} override, then the DT audit
     * session organization (set by the inspector's organization selector),
     * then an explicit tenant-switcher override, and finally the
     * authenticated user's organization.
     */
    public static function id(): ?int
    {
        if (self::$override !== null) {
            return self::$override;
        }

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
     * Run `$callback` with the current organization forced to
     * `$organizationId`, for a context with no session/auth to resolve it
     * from. Always restores whatever was set before, even if `$callback`
     * throws, so a nested or repeated call never leaks its override.
     */
    public static function runAs(int $organizationId, callable $callback): mixed
    {
        $previous = self::$override;
        self::$override = $organizationId;

        try {
            return $callback();
        } finally {
            self::$override = $previous;
        }
    }
}
