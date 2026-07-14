<?php

namespace App\Notifications;

use App\Models\MarkModification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee when an admin requests a correction to one of their
 * attendance marks. The email carries a link to the public, no-auth review
 * page (keyed by the modification's ULID) where the employee approves or
 * declines the change.
 */
class MarkModificationRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly MarkModification $markModification) {}

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
            ->subject(__('mail.mark_modification_requested.subject'))
            ->markdown('mail.mark-modifications.requested', [
                'markModification' => $this->markModification,
                'reviewUrl' => url("/mark-modifications/{$this->markModification->ulid}"),
            ]);
    }
}
