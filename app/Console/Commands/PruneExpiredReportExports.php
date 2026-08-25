<?php

namespace App\Console\Commands;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('report-exports:prune-expired')]
#[Description('Delete the file and row for every queued report export past its signed link expiry (KOL-16 AC #5), so finished exports do not accumulate on disk indefinitely.')]
class PruneExpiredReportExports extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = ReportExport::query()
            ->where('status', ReportExportStatus::Ready)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $reportExport) {
            if ($reportExport->disk_path !== null) {
                Storage::disk('local')->delete($reportExport->disk_path);
            }

            $reportExport->delete();
        }

        $this->info("Pruned {$expired->count()} expired report export(s).");

        return self::SUCCESS;
    }
}
