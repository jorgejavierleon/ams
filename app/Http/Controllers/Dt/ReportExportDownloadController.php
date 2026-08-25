<?php

namespace App\Http\Controllers\Dt;

use App\Actions\Reports\DownloadReportExport;
use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivers the finished file for a queued report export (KOL-16).
 *
 * The signed link mailed to the requester lands on {@see show}, a real HTML
 * page with a "Descargar" button — pointing a fresh browser tab straight at a
 * raw file response leaves the tab blank with nothing to display, and Chrome
 * only completes the download on a manual refresh, which is not something an
 * end user would ever discover on their own. {@see download} then serves the
 * file exactly like the DT documents download does: authenticated and
 * organization-scoped, no signature required for it specifically (KOL-16 AC
 * #4 asks for an authenticated *or* signed route, and by the time a request
 * reaches this action it has already passed both auth and the signed landing
 * page).
 */
class ReportExportDownloadController extends Controller
{
    public function show(ReportExport $reportExport): View
    {
        return view('dt.reports.export-ready', [
            'reportExport' => $reportExport,
            'expired' => $reportExport->expires_at !== null && $reportExport->expires_at->isPast(),
        ]);
    }

    public function download(ReportExport $reportExport, DownloadReportExport $download): Response
    {
        return $download->handle($reportExport);
    }
}
