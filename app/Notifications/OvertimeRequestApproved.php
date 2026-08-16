<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requesting employee when their overtime request has been
 * approved.
 */
class OvertimeRequestApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly OvertimeRequest $overtimeRequest) {}

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
            ->subject(__('mail.overtime_request_approved.subject'))
            ->markdown('mail.overtime-requests.approved', [
                'overtimeRequest' => $this->overtimeRequest,
                'url' => route('my.overtime-requests.index'),
            ]);
    }
}
