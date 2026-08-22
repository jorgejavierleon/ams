<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an overtime request's approver(s) — the requester's supervisor when
 * they may approve, otherwise the organization admins — when a request is
 * submitted.
 */
class OvertimeRequestSubmitted extends Notification implements ShouldQueue
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
            ->subject(__('mail.overtime_request_submitted.subject'))
            ->markdown('mail.overtime-requests.submitted', [
                'overtimeRequest' => $this->overtimeRequest,
                'url' => route('overtime.requests.index'),
            ]);
    }
}
