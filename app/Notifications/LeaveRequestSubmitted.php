<?php

namespace App\Notifications;

use App\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a leave's approver(s) — the requester's supervisor when they may
 * approve, otherwise the organization admins — when a request is submitted.
 */
class LeaveRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Leave $leave) {}

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
            ->subject(__('mail.leave_submitted.subject'))
            ->markdown('mail.leaves.submitted', [
                'leave' => $this->leave,
                'url' => route('leaves.index'),
            ]);
    }
}
