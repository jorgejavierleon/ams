<?php

namespace App\Observers;

use App\Models\EmailLimitCrossing;
use App\Models\Organization;
use Illuminate\Support\Carbon;

/**
 * Keeps the KOL-137.3 hard-limit crossing record in step with the hard limit
 * itself (KOL-137.5). SuppressEmailOverHardLimit short-circuits straight to
 * "suppress" once this month's hard-limit crossing is recorded, skipping the
 * monthly send COUNT entirely - but only while hard_email_limit_override is
 * set, which is exactly the one column this observer watches. An admin
 * raising the override mid-month would otherwise leave the organization
 * suppressed forever this month even though it is now back under its (new,
 * higher) limit; an organization on the default, computed limit never takes
 * this short-circuit at all; see SuppressEmailOverHardLimit's docblock for
 * why that pair of facts has to line up.
 *
 * Deliberately does not touch soft-limit crossings: the soft limit never
 * suppresses, so EmailLimitCrossing::TYPE_SOFT exists purely as a
 * once-per-month alert ("this organization crossed its soft limit at some
 * point this month"), a historical fact a later override change does not
 * retroactively undo.
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
            ->where('month', EmailLimitCrossing::monthKey(Carbon::now()))
            ->delete();
    }
}
