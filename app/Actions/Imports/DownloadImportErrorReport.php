<?php

namespace App\Actions\Imports;

use App\Actions\Reports\DownloadReportExport;
use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a commit pass's error-report CSV from the private disk (KOL-94.8,
 * KOL-103) — refused once the run has nothing to report, mirroring
 * {@see DownloadReportExport}'s same not-ready guard. Only Completed or
 * Failed is allowed: ProcessImportRun's writer truncates and rewrites the
 * file on every attempt (see ImportErrorReportWriter::open()), so a run
 * still Processing could have the file mid-rewrite underneath this request.
 * Both terminal statuses are safe to read from — the writer's try/finally
 * always closes the file for the attempt that left the run in either state.
 */
class DownloadImportErrorReport
{
    public function handle(ImportRun $importRun): Response
    {
        abort_unless(
            in_array($importRun->status, [ImportRunStatus::Completed, ImportRunStatus::Failed], true)
                && $importRun->errored_count > 0,
            404,
        );

        return Storage::disk('local')->download(
            $importRun->error_report_path,
            "{$importRun->id}-errores.csv",
        );
    }
}
