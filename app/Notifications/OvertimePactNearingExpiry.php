<?php

namespace App\Notifications;

use App\Models\OvertimePact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every user holding `Manage:OvertimeAuthorization` in a pacto's
 * organization when its `end_date` is within the near-expiry window
 * (KOL-42 AC #3), so a renewal can be arranged before it lapses.
 */
class OvertimePactNearingExpiry extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly OvertimePact $pact) {}

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
            ->subject(__('mail.overtime_pact_nearing_expiry.subject'))
            ->markdown('mail.overtime.pact-nearing-expiry', [
                'pact' => $this->pact,
                'url' => route('overtime.pacts.index'),
            ]);
    }
}
