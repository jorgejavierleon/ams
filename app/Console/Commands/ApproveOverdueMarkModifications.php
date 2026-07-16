<?php

namespace App\Console\Commands;

use App\Managers\MarkModificationManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mark-modifications:approve-overdue')]
#[Description('Consolidate pending mark modifications whose 48h opposition window has closed (Resolución 38, art. 40 d).')]
class ApproveOverdueMarkModifications extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MarkModificationManager $manager): int
    {
        $approved = $manager->approveOverdueModifications();

        $this->info("Consolidated {$approved} overdue mark modification(s).");

        return self::SUCCESS;
    }
}
