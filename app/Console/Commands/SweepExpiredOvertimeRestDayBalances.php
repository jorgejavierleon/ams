<?php

namespace App\Console\Commands;

use App\Services\Overtime\RestDayBalanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('overtime:rest-day-balances:sweep-expired')]
#[Description('Stamp rest-day balance lines past their six-month expiry (KOL-47 AC #3), so their remainder becomes payable instead of forfeited (art. 32 §4).')]
class SweepExpiredOvertimeRestDayBalances extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RestDayBalanceService $balances): int
    {
        $expired = $balances->sweepExpired();

        $this->info("Expired {$expired} rest-day balance line(s).");

        return self::SUCCESS;
    }
}
