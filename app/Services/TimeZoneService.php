<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
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
     * The calendar date it currently is where the application's users are.
     *
     * The server runs in UTC, so between 21:00 and midnight Chilean time
     * `now()->toDateString()` is already tomorrow. Anything asking "what rule
     * applies today" has to ask about the day the user is actually living.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->getAppTimezone())->startOfDay();
    }

    /**
     * The timezone for the given user, or the authenticated user when omitted.
     */
    public function getUserTimezone(?User $user = null): string
    {
        $user ??= Auth::user();

        return $user->timezone ?? $this->getAppTimezone();
    }
}
