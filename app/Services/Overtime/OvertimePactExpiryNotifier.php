<?php

namespace App\Services\Overtime;

use App\Models\OvertimePact;
use App\Models\User;
use App\Notifications\OvertimePactNearingExpiry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * The near-expiry alert of KOL-42 AC #3: a pacto within {@see self::WITHIN_DAYS}
 * of its `end_date` notifies every user who holds `Manage:OvertimeAuthorization`
 * in its organization, so an agreement does not lapse silently mid-period.
 *
 * Notified once per pacto: {@see OvertimePact::$expiry_notified_at} is stamped
 * on send, and {@see OvertimePact::scopeNearingExpiry()} excludes anything
 * already stamped, so the daily schedule never repeats an alert for the same
 * agreement.
 */
class OvertimePactExpiryNotifier
{
    /**
     * No AC specifies the exact window; a week gives whoever manages pactos
     * enough runway to arrange a renewal before the agreement lapses.
     */
    private const WITHIN_DAYS = 7;

    /**
     * @return int Pactos notified about.
     */
    public function notifyExpiring(): int
    {
        $pacts = OvertimePact::query()
            ->with('user:id,name')
            ->nearingExpiry(self::WITHIN_DAYS)
            ->get();

        foreach ($pacts as $pact) {
            Notification::send($this->recipients($pact), new OvertimePactNearingExpiry($pact));

            $pact->markExpiryNotified();
        }

        return $pacts->count();
    }

    /**
     * Every user who may manage pactos for this agreement's organization.
     *
     * @return Collection<int, User>
     */
    private function recipients(OvertimePact $pact): Collection
    {
        return User::query()
            ->permission('Manage:OvertimeAuthorization')
            ->where('organization_id', $pact->organization_id)
            ->get();
    }
}
