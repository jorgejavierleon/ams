<?php

namespace App\Services;

use App\Models\Setting;
use App\Observers\SettingObserver;
use Illuminate\Support\Facades\Cache;

/**
 * Single read/access point for an organization's {@see Setting} row. The row is
 * created on first access with the schema defaults, so callers always receive a
 * usable settings object.
 *
 * Scalar reads are cached per organization as a plain attributes array — never
 * the Eloquent model itself, which does not survive serialization into a real
 * cache store (it round-trips to `__PHP_Incomplete_Class`). The cache is
 * invalidated by {@see SettingObserver} whenever a row changes.
 */
class OrganizationSettings
{
    private const CACHE_KEY_FORMAT = 'org_settings:%s';

    private const CACHE_TTL_DAYS = 7;

    /**
     * The settings row for the given organization (defaults to the current
     * tenant), created with defaults on first access. Returns a live model so
     * callers can read and persist it — this is intentionally uncached.
     */
    public function current(?int $organizationId = null): Setting
    {
        $organizationId ??= Setting::currentOrganizationId();

        return Setting::query()->firstOrCreate(['organization_id' => $organizationId]);
    }

    /**
     * Read a single setting value off the cached attributes array, falling back
     * to $default when unknown. This is the hot path for feature code that just
     * needs a value without touching the database on every read.
     */
    public function get(string $key, mixed $default = null, ?int $organizationId = null): mixed
    {
        $organizationId ??= Setting::currentOrganizationId();

        $attributes = Cache::remember(
            $this->cacheKey($organizationId),
            now()->addDays(self::CACHE_TTL_DAYS),
            fn (): array => $this->current($organizationId)->attributesToArray(),
        );

        return $attributes[$key] ?? $default;
    }

    /**
     * Drop the cached settings for an organization so the next read reloads it.
     * Called by the observer on every change.
     */
    public function forgetCache(?int $organizationId = null): void
    {
        Cache::forget($this->cacheKey($organizationId));
    }

    private function cacheKey(?int $organizationId): string
    {
        return sprintf(self::CACHE_KEY_FORMAT, $organizationId);
    }
}
