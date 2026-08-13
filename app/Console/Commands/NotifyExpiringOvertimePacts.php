<?php

namespace App\Console\Commands;

use App\Services\Overtime\OvertimePactExpiryNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('overtime:pacts:notify-expiring')]
#[Description('Alert whoever manages pactos when one is nearing its end date (KOL-42 AC #3).')]
class NotifyExpiringOvertimePacts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OvertimePactExpiryNotifier $notifier): int
    {
        $notified = $notifier->notifyExpiring();

        $this->info("Notified about {$notified} pacto(s) nearing expiry.");

        return self::SUCCESS;
    }
}
