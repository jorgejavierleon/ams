<?php

namespace App\Listeners;

use App\Models\EmailSend;
use Illuminate\Mail\Events\MessageSent;

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
 */
class RecordEmailSend
{
    public function handle(MessageSent $event): void
    {
        $header = $event->message->getHeaders()->get('X-Metadata-organization_id');

        if ($header === null) {
            return;
        }

        EmailSend::create(['organization_id' => (int) $header->getBody()]);
    }
}
