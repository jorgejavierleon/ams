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
 * Once this month's hard-limit crossing is recorded, every later send for
 * this organization short-circuits straight to "suppress" without paying for
 * the monthly COUNT (KOL-137.5) - safe because {@see OrganizationObserver}
 * clears that record the moment hard_email_limit_override changes, so a
 * raised limit always falls back through to a fresh count. Before that
 * crossing exists, the count-then-decide-then-record step runs inside a
 * per-organization lock: without it, a burst of concurrent queued sends can
 * all read the same stale "under the limit" count and all proceed, since the
 * EmailSend row each one implies is only written later, after the real
 * transport call, by {@see RecordEmailSend}. The lock cannot close that gap
 * entirely - sends already past this check when the limit is reached are
 * already "in flight" and cannot be recalled - but it does serialize the
 * decision itself, bounding the overshoot to those in-flight sends instead
 * of leaving it unbounded.
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

        if ($organization->hasCrossedEmailLimitThisMonth(EmailLimitCrossing::TYPE_HARD, $month)) {
            return false;
        }

        return Cache::lock("organization:{$organization->id}:hard-email-limit-check", 10)
            ->block(5, function () use ($organization, $hardLimit, $month): bool {
                if ($organization->emailSendsCountForMonth($month) < $hardLimit) {
                    return true;
                }

                $organization->recordEmailLimitCrossing(EmailLimitCrossing::TYPE_HARD, $month);

                return false;
            });
    }
}
