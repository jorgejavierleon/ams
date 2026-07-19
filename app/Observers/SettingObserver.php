<?php

namespace App\Observers;

use App\Models\Setting;
use App\Services\OrganizationSettings;

/**
 * Keeps the cached organization settings in sync: any time a {@see Setting} row
 * is created or updated its organization's cache entry is dropped so the next
 * read reflects the change. Writes must go through Eloquent (never a raw query
 * builder update) or this invalidation is bypassed and reads go stale.
 */
class SettingObserver
{
    public function __construct(
        private OrganizationSettings $organizationSettings,
    ) {}

    public function saved(Setting $setting): void
    {
        $this->organizationSettings->forgetCache($setting->organization_id);
    }
}
