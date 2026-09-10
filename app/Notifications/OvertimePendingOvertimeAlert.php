<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The stale-overtime digest of PRD §12 (KOL-52): sent to whoever manages
 * overtime for an organization while it still has a shift excess with no
 * approved decision past the configured threshold. Sent again on every run
 * the condition holds — deliberately not one-shot like
 * {@see OvertimePactNearingExpiry} — so inaction stays visible for as long
 * as it lasts, rather than being acknowledged away after a single email.
 */
class OvertimePendingOvertimeAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $staleCount,
        public readonly int $thresholdDays,
        public readonly int $oldestDaysPending,
    ) {}

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
            ->subject(__('mail.overtime_pending_alert.subject'))
            ->markdown('mail.overtime.pending-alert', [
                'staleCount' => $this->staleCount,
                'thresholdDays' => $this->thresholdDays,
                'oldestDaysPending' => $this->oldestDaysPending,
                'url' => route('overtime.pending.index'),
            ]);
    }
}
