<?php

namespace App\Services\Overtime;

use App\Models\OvertimeRestDayBalance;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\RestDayBalanceAccrued;
use Illuminate\Support\Facades\Notification;

/**
 * The recurring balance alert of KOL-48 (Resolución 38 art. 45.3): every 30
 * days, an employee carrying rest-day compensation balance is mailed their
 * accrued hours and each accrual's expiry date, so time off earned is not
 * lost to a forgotten deadline.
 *
 * Deliberately not gated by a per-tenant toggle, unlike the other
 * notification switches on {@see Setting} — this is a hard legal
 * requirement (art. 45.3), not a convenience a tenant may opt out of.
 *
 * Notified once per 30-day window per employee:
 * {@see User::$rest_day_balance_notified_at} is stamped on send and
 * {@see User::scopeDueForRestDayBalanceNotification()} excludes anyone
 * stamped within the window, so the daily schedule never repeats an alert
 * before the cadence elapses, and a same-day re-run cannot double-mail.
 */
class RestDayBalanceNotifier
{
    /**
     * @return int Employees notified.
     */
    public function notifyDue(): int
    {
        $users = User::query()
            ->dueForRestDayBalanceNotification()
            ->get();

        foreach ($users as $user) {
            $balances = OvertimeRestDayBalance::query()
                ->forUser($user->id)
                ->spendable()
                ->get();

            Notification::send($user, new RestDayBalanceAccrued($balances));

            $user->markRestDayBalanceNotified();
        }

        return $users->count();
    }
}
