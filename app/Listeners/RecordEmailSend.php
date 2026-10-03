<?php

namespace App\Listeners;

use App\Models\EmailLimitCrossing;
use App\Models\EmailSend;
use App\Models\Organization;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Carbon;

/**
 * Central hook for KOL-137.1: Illuminate\Mail\Events\MessageSent fires for
 * every outgoing message regardless of whether it was sent as a raw Mailable
 * or via a Notification's mail channel.
 *
 * Attribution comes from the organization_id metadata each tracked
 * Mail/Notification class declares on its Envelope/MailMessage — never from
 * the recipient address, since mail may route to a user's personal_email,
 * which differs from their account email (KOL-62). A message with no such
 * metadata is not tracked; this is what excludes DT mail (DtAuditNotification,
 * SendDtPassword) without the listener needing to know about those classes.
 *
 * Also raises the KOL-137.3 soft-limit alert here, since this event only
 * fires for sends that actually went out (a send suppressed by
 * {@see SuppressEmailOverHardLimit} never reaches MessageSent).
 */
class RecordEmailSend
{
    public function handle(MessageSent $event): void
    {
        $header = $event->message->getHeaders()->get('X-Metadata-organization_id');

        if ($header === null) {
            return;
        }

        $organizationId = (int) $header->getBody();

        EmailSend::create(['organization_id' => $organizationId]);

        $this->raiseSoftLimitAlertIfCrossed($organizationId);
    }

    private function raiseSoftLimitAlertIfCrossed(int $organizationId): void
    {
        $organization = Organization::find($organizationId);

        if ($organization === null) {
            return;
        }

        $softLimit = $organization->softEmailLimit();

        if ($softLimit <= 0) {
            return;
        }

        if ($organization->emailSendsCountForMonth(Carbon::now()) < $softLimit) {
            return;
        }

        $organization->recordEmailLimitCrossing(EmailLimitCrossing::TYPE_SOFT);
    }
}
