<?php

namespace App\Jobs;

use App\Enums\ReportExportStatus;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Notifications\ReportExportFailed;
use App\Notifications\ReportExportReady;
use App\Services\Reports\DtReportExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders a queued report export in the background (KOL-16, PRD §7
 * performance NFR) and delivers it to the requester by a signed download
 * link, so a large export never ties up the request thread.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $reportExportId) {}

    public function handle(DtReportExporter $exporter): void
    {
        $reportExport = ReportExport::findOrFail($this->reportExportId);
        $reportExport->update(['status' => ReportExportStatus::Processing]);

        $organization = Organization::findOrFail($reportExport->organization_id);
        $filters = $reportExport->filters;

        $rendered = $exporter->renderToBytes(
            $reportExport->type,
            $reportExport->format,
            Carbon::parse($filters['start']),
            Carbon::parse($filters['end']),
            $filters['user_ids'],
            $organization,
        );

        $diskPath = "report-exports/{$reportExport->organization_id}/{$reportExport->getKey()}-{$rendered['filename']}";
        Storage::disk('local')->put($diskPath, $rendered['contents']);

        $reportExport->update([
            'status' => ReportExportStatus::Ready,
            'disk_path' => $diskPath,
            'filename' => $rendered['filename'],
            'expires_at' => now()->addMinutes(config('reports.export.link_expiry_minutes')),
        ]);

        $reportExport->user->notify(new ReportExportReady($reportExport));
    }

    /**
     * A failed export must not leave the requester waiting for a
     * notification that never arrives (KOL-16 AC #8).
     */
    public function failed(?Throwable $exception): void
    {
        $reportExport = ReportExport::find($this->reportExportId);

        if ($reportExport === null) {
            return;
        }

        $reportExport->update([
            'status' => ReportExportStatus::Failed,
            'failure_reason' => $exception?->getMessage(),
        ]);

        $reportExport->user->notify(new ReportExportFailed($reportExport));
    }

    /**
     * Tags so a stuck export is legible in Horizon/Telescope without
     * opening the payload.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['report-export:'.$this->reportExportId];
    }
}
