<?php

namespace App\Listeners;

use App\Models\EmailLimitCrossing;
use App\Models\Organization;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Enforcement half of KOL-137.3 (plus the KOL-137.4 manual override),
 * mirroring {@see RecordEmailSend}'s header read but hooked on MessageSending
 * rather than MessageSent: returning false here tells
 * Illuminate\Mail\Mailer::shouldSendMessage() to skip the actual transport
 * call, so the suppressed send never reaches MessageSent and is never
 * recorded as an EmailSend. This runs before every send, unlike the
 * after-the-fact recorder, which is the only place a send can still be
 * stopped.
 *
 * Both checks must live in this one listener: Illuminate's event dispatcher
 * halts on MessageSending at the first listener whose response isn't null,
 * so a second MessageSending listener returning a plain bool would never
 * run.
 *
 * The KOL-137.4 manual override is checked first and, when set, wins
 * unconditionally in both directions - a forced-off organization is
 * suppressed even under its limits, and a forced-on organization bypasses
 * the hard-limit check below entirely. Null (never manually touched) falls
 * through to that automatic check.
 *
 * A hard limit of 0 means the baseline/override is simply unset (KOL-137.2),
 * not a deliberate zero-email cap, so it is treated as "not configured yet"
 * and never suppresses.
 *
 * Once an admin-configured hard_email_limit_override has already been
 * recorded as crossed this month, every later send short-circuits straight
 * to "suppress" without paying for the monthly COUNT (KOL-137.5). This is
 * deliberately scoped to the override case only: {@see OrganizationObserver}
 * clears that record the instant hard_email_limit_override changes, which is
 * a single column with a single point of change - but hardEmailLimit()'s
 * other source, defaultEmailLimit(), is a live computation over active user
 * count and the platform-wide baseline with no equivalent single point to
 * observe. An organization with no override always pays for a fresh COUNT,
 * exactly as it did before KOL-137.5, so it can never go stale.
 *
 * The decide-and-record step only runs once an organization is already at
 * or over its limit (the COUNT just above is unlocked and cheap for every
 * send comfortably under it), and runs inside a per-organization lock so a
 * burst of concurrent queued sends right at the boundary doesn't all read
 * the same stale count and all proceed at once. That lock is attempted
 * without blocking: if it is already held, this send decides unlocked
 * rather than waiting on it and risking a LockTimeoutException deep inside
 * Mailer::shouldSendMessage(), which would fail the send outright instead of
 * cleanly allowing or suppressing it. Either way, the lock is a mitigation,
 * not a guarantee: the EmailSend row each passing send implies is only
 * written later, after the real transport call, by {@see RecordEmailSend},
 * so sends that pass concurrently before any of them lands can still push
 * the total past the limit by roughly as many as are genuinely in flight at
 * once - it bounds the thundering-herd case, not every possible interleaving.
 */
class SuppressEmailOverHardLimit
{
    public function handle(MessageSending $event): bool
    {
        $header = $event->message->getHeaders()->get('X-Metadata-organization_id');

        if ($header === null) {
            return true;
        }

        $organization = Organization::find((int) $header->getBody());

        if ($organization === null) {
            return true;
        }

        if ($organization->emailSendingManuallyDisabled()) {
            return false;
        }

        if ($organization->emailSendingManuallyEnabled()) {
            return true;
        }

        $hardLimit = $organization->hardEmailLimit();

        if ($hardLimit <= 0) {
            return true;
        }

        $month = Carbon::now();

        if ($organization->hard_email_limit_override !== null
            && $organization->hasCrossedEmailLimitThisMonth(EmailLimitCrossing::TYPE_HARD, $month)) {
            return false;
        }

        if ($organization->emailSendsCountForMonth($month) < $hardLimit) {
            return true;
        }

        $decide = function () use ($organization, $hardLimit, $month): bool {
            if ($organization->emailSendsCountForMonth($month) < $hardLimit) {
                return true;
            }

            $organization->recordEmailLimitCrossing(EmailLimitCrossing::TYPE_HARD, $month);

            return false;
        };

        $lock = Cache::lock(self::lockKeyFor($organization->id), 10);

        if (! $lock->get()) {
            return $decide();
        }

        try {
            return $decide();
        } finally {
            $lock->release();
        }
    }

    /**
     * The per-organization lock name serializing the decide-and-record step
     * above, exposed so tests can exercise contention against the exact key
     * production uses rather than a copy that could silently drift from it.
     */
    public static function lockKeyFor(int $organizationId): string
    {
        return "organization:{$organizationId}:hard-email-limit-check";
    }
}
