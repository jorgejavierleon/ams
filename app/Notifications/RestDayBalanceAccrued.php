<?php

namespace App\Notifications;

use App\Models\OvertimeRestDayBalance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent to an employee holding rest-day compensation balance (KOL-48,
 * Resolución 38 art. 45.3): the accrued hours and the date each accrual
 * expires, so time off earned is not lost to a forgotten deadline.
 */
class RestDayBalanceAccrued extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, OvertimeRestDayBalance>  $balances  Spendable lines, oldest expiry first.
     */
    public function __construct(public readonly Collection $balances) {}

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
            ->subject(__('mail.rest_day_balance_accrued.subject'))
            ->markdown('mail.overtime.rest-day-balance-accrued', [
                'balances' => $this->balances,
                'url' => route('my.overtime-rest-day-balance.index'),
            ]);
    }
}
