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
 * Carries the one-time code a signatory enters to author their firma
 * electrónica simple on a document. Sent to the signatory's personal email;
 * the code expires shortly after issue.
 */
class DocumentSignatureVerificationCode extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public string $verificationCode,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.document_signature_verification_code.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.documents.signature-verification-code',
            with: [
                'title' => $this->document->title,
                'code' => $this->verificationCode,
            ],
        );
    }
}
