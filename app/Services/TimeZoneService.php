<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the timezone attendance marks should be timestamped in. Each
 * employee carries their own `timezone`; everything falls back to the
 * application display timezone (America/Santiago) so punches read correctly
 * regardless of where the server runs.
 */
class TimeZoneService
{
    /**
     * The application's default display timezone.
     */
    public function getAppTimezone(): string
    {
        return config('app.timezone_display') ?? config('app.timezone');
    }

    /**
     * The timezone for the given user, or the authenticated user when omitted.
     */
    public function getUserTimezone(?User $user = null): string
    {
        $user ??= Auth::user();

        return $user?->timezone ?? $this->getAppTimezone();
    }
}
