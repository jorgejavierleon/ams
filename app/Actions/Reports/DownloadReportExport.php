<?php

namespace App\Actions\Reports;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a queued export's finished file from the private disk (KOL-16 AC
 * #4), refusing anything not yet ready or whose signed link has lapsed (AC
 * #5) even if a stale link were somehow still reachable.
 */
class DownloadReportExport
{
    public function handle(ReportExport $reportExport): Response
    {
        abort_unless($reportExport->status === ReportExportStatus::Ready, 404);
        abort_if($reportExport->expires_at !== null && $reportExport->expires_at->isPast(), 410);

        return Storage::disk('local')->download($reportExport->disk_path, $reportExport->filename);
    }
}
