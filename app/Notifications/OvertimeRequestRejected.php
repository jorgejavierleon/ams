<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requesting employee when their overtime request has been
 * rejected. Does not stop them from working the day — it only means the
 * hours, if worked, arrive at the queue without a prior request behind them.
 */
class OvertimeRequestRejected extends Notification implements ShouldQueue
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
            ->subject(__('mail.overtime_request_rejected.subject'))
            ->markdown('mail.overtime-requests.rejected', [
                'overtimeRequest' => $this->overtimeRequest,
                'url' => route('my.overtime-requests.index'),
            ]);
    }
}
