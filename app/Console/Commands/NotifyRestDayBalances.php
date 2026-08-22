<?php

namespace App\Console\Commands;

use App\Services\Overtime\RestDayBalanceNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('overtime:rest-day-balances:notify')]
#[Description('Mail every employee carrying rest-day balance their accrued hours and expiry dates (KOL-48, Resolución 38 art. 45.3).')]
class NotifyRestDayBalances extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RestDayBalanceNotifier $notifier): int
    {
        $notified = $notifier->notifyDue();

        $this->info("Notified {$notified} employee(s) about their rest-day balance.");

        return self::SUCCESS;
    }
}
