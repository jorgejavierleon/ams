<?php

namespace App\Notifications;

use App\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requesting employee when their leave has been rejected.
 */
class LeaveRejected extends Notification implements ShouldQueue
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
            ->subject(__('mail.leave_rejected.subject'))
            ->markdown('mail.leaves.rejected', [
                'leave' => $this->leave,
                'url' => route('my.leaves.index'),
            ]);
    }
}
