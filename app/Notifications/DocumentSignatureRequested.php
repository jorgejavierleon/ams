<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to each configured signatory when a document is published, inviting
 * them to review and sign it.
 */
class DocumentSignatureRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Document $document) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.document_signature_requested.subject'))
            ->markdown('mail.documents.signature-requested', [
                'document' => $this->document,
            ]);
    }
}
