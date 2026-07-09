<?php

namespace App\Console\Commands;

use App\Actions\SyncOfficialHolidays as SyncOfficialHolidaysAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('holidays:sync {year? : The year to import (defaults to the current year)}')]
#[Description("Import Chile's official public holidays from the Boostr API.")]
class SyncOfficialHolidays extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncOfficialHolidaysAction $sync): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);

        $this->info("Importing official holidays for {$year}...");

        try {
            $count = $sync->handle($year);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$count} holidays.");

        return self::SUCCESS;
    }
}
