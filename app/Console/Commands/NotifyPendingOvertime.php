<?php

namespace App\Console\Commands;

use App\Services\Overtime\OvertimePendingAlertNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('overtime:pending:notify-stale')]
#[Description('Alert whoever manages overtime when a shift excess has gone undecided past the configured threshold (PRD §12, KOL-52).')]
class NotifyPendingOvertime extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OvertimePendingAlertNotifier $notifier): int
    {
        $notified = $notifier->notifyStale();

        $this->info("Notified {$notified} organization(s) about stale pending overtime.");

        return self::SUCCESS;
    }
}
