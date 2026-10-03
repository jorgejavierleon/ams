<?php

namespace App\Observers;

use App\Models\EmailLimitCrossing;
use App\Models\Organization;
use Illuminate\Support\Carbon;

/**
 * Keeps the KOL-137.3 hard-limit crossing record in step with the hard limit
 * itself (KOL-137.5). SuppressEmailOverHardLimit short-circuits straight to
 * "suppress" once this month's hard-limit crossing is recorded, skipping the
 * monthly send COUNT entirely. Without this observer that short-circuit
 * would never notice an admin raising hard_email_limit_override mid-month,
 * leaving the organization suppressed even though it is now back under its
 * (new, higher) limit.
 */
class OrganizationObserver
{
    public function saved(Organization $organization): void
    {
        if (! $organization->wasChanged('hard_email_limit_override')) {
            return;
        }

        $organization->emailLimitCrossings()
            ->where('type', EmailLimitCrossing::TYPE_HARD)
            ->where('month', Carbon::now()->startOfMonth()->toDateString())
            ->delete();
    }
}
