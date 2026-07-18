<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the employee once every signatory has signed a document, confirming
 * completion. The stored, signed PDF is the authoritative copy they can then
 * download for their records (Resolución 38 art. 22.1 permanent access).
 */
class DocumentFullySigned extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.document_fully_signed.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.documents.fully-signed',
            with: [
                'title' => $this->document->title,
            ],
        );
    }
}
