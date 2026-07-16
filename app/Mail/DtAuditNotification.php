<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Non-nominative notice sent to an employer when a DT inspector begins auditing
 * their records, as mandated by Resolución 38 (Art. 24 b). The message carries
 * no inspector-identifying data and its wording is fixed by the regulation.
 */
class DtAuditNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Inicio de un procedimiento de fiscalización laboral',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.dt.audit-notification',
        );
    }
}
