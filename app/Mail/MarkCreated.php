<?php

namespace App\Mail;

use App\Models\Mark;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Receipt sent to an employee after they register an attendance punch, giving
 * them a personal copy with the integrity checksum for their records.
 *
 * It shows the same folio as the API response the mobile app draws its receipt
 * from: the number an employee quotes to HR may not differ between the copy in
 * their inbox and the one on their phone.
 */
class MarkCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Mark $mark) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: __('mail.mark_created.subject'),
        );

        // organization_id is nullable on offline-captured marks (KOL-137.1):
        // skip metadata rather than send a non-numeric X-Metadata header.
        if ($this->mark->organization_id !== null) {
            $envelope->metadata('organization_id', (string) $this->mark->organization_id);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.mark-created',
            with: [
                'folio' => $this->mark->folio,
                'type' => $this->mark->type->label(),
                'dateTime' => $this->mark->date_time->format('d-m-Y H:i'),
                'checksum' => $this->mark->checksum,
            ],
        );
    }
}
