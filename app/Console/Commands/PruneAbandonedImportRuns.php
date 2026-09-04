<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('import-runs:prune-abandoned')]
#[Description('Delete the file and row for every Employee import run left in Pending, MappingReview, or PreviewReady past its expiry (KOL-104), so abandoned uploads do not accumulate on disk indefinitely.')]
class PruneAbandonedImportRuns extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $abandoned = ImportRun::query()
            ->whereIn('status', [ImportRunStatus::Pending, ImportRunStatus::MappingReview, ImportRunStatus::PreviewReady])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($abandoned as $importRun) {
            if ($importRun->disk_path !== null) {
                Storage::disk('local')->delete($importRun->disk_path);
            }

            $importRun->delete();
        }

        $this->info("Pruned {$abandoned->count()} abandoned import run(s).");

        return self::SUCCESS;
    }
}
