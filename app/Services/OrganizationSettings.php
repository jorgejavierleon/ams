<?php

namespace App\Services;

use App\Enums\OvertimeAuthorizationMode;
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

        // Nothing to attach a row to. The unsaved model still carries the schema
        // defaults, so reads keep working and no orphan row is written.
        if ($organizationId === null) {
            return new Setting;
        }

        $setting = Setting::query()->firstOrNew(['organization_id' => $organizationId]);

        if (! $setting->exists) {
            // `organization_id` is deliberately absent from the model's fillable
            // list, so mass assignment cannot move a settings row between
            // tenants — which also means it has to be stamped by hand here. The
            // creating hook only covers the case of an active tenant, and the
            // calculation engine reads these settings from the console, where
            // there is none.
            $setting->organization_id = $organizationId;
            $setting->save();
        }

        return $setting;
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
     * The organization's overtime authorisation mode (PRD §7.1), read off the
     * cached attributes array so calculation code can consult it per workday
     * without a query. Falls back to the schema default if the stored value is
     * not a mode the application knows.
     */
    public function overtimeAuthorizationMode(?int $organizationId = null): OvertimeAuthorizationMode
    {
        $value = $this->get('overtime_authorization_mode', organizationId: $organizationId);

        return OvertimeAuthorizationMode::tryFrom((string) $value) ?? OvertimeAuthorizationMode::PostHoc;
    }

    /**
     * Whether an early arrival contributes to the calculated overtime (PRD
     * §7.2). Read per workday by the calculation engine, so it comes off the
     * cached attributes array rather than the database. Off unless the tenant
     * has said otherwise — art. 32 wants the employer's knowledge behind excess
     * hours, and an unrequested early arrival carries none.
     */
    public function overtimeCountsPreShiftExcess(?int $organizationId = null): bool
    {
        return (bool) $this->get('overtime_counts_pre_shift_excess', false, $organizationId);
    }

    /**
     * The weekly overtime volume above which a week is flagged as anomalous
     * (PRD §7.4), read off the cached attributes array so the anomaly check can
     * be made per workday without a query. Per-tenant because a legitimate
     * spike in a critical shift is not the same signal as in an office.
     */
    public function overtimeWeeklyAnomalyThresholdHours(?int $organizationId = null): float
    {
        return (float) $this->get('overtime_weekly_anomaly_threshold_hours', 10.0, $organizationId);
    }

    /**
     * How many days into the past an employee may still request overtime for
     * (Mode A, KOL-45). A request for a date older than this is refused with a
     * Spanish message naming the window.
     */
    public function overtimeRetroactiveRequestDays(?int $organizationId = null): int
    {
        return (int) $this->get('overtime_retroactive_request_days', 7, $organizationId);
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
