<?php

namespace App\Services\Reports;

use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns any of the five Resolución 38 reports into a downloadable Excel, PDF or
 * Word file (Art. 28 b) via the shared ReportWriter. Each report is rendered
 * once to an HTML table by its Blade view — the single source that also drives
 * the on-screen React table — so every format is identical to the others and
 * to the screen (Art. 28 a). ReportWriter applies Arial 8 to the spreadsheet
 * and document writers (Art. 28 e); the Blade styles cover the PDF.
 *
 * The report is always emitted in Spanish, as the reports must be in castellano
 * (Art. 5), regardless of the inspector's chosen interface locale.
 */
class DtReportExporter
{
    /**
     * The export formats offered for every report (Art. 28 b).
     *
     * @var list<string>
     */
    public const FORMATS = ['excel', 'pdf', 'word'];

    public function __construct(
        private AttendanceReportService $attendance,
        private DailyReportService $daily,
        private ShiftChangesReportService $shiftChanges,
        private SundaysReportService $sundays,
        private IncidentsReportService $incidents,
        private ReportWriter $writer,
    ) {}

    /**
     * Build the requested report and stream it back in the requested format.
     *
     * @param  list<int>  $userIds  the workers the report covers (empty for the
     *                              per-employer incidents log, Art. 24 d)
     */
    public function download(
        string $type,
        string $format,
        Carbon $start,
        Carbon $end,
        array $userIds,
        Organization $organization,
    ): Response {
        ['fragment' => $fragment, 'filename' => $filename] = $this->prepare($type, $start, $end, $userIds, $organization);

        return match ($format) {
            'excel' => $this->writer->excel($this->document($fragment, $type), $filename),
            'pdf' => $this->writer->pdf($this->document($fragment, $type), $filename),
            'word' => $this->writer->word($fragment, $filename),
            default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * Build the requested report and render it to raw bytes rather than an
     * HTTP response, for a queued export that must save the file to disk
     * (KOL-16) instead of streaming it to a browser.
     *
     * @param  list<int>  $userIds
     * @return array{filename: string, mime: string, contents: string}
     */
    public function renderToBytes(
        string $type,
        string $format,
        Carbon $start,
        Carbon $end,
        array $userIds,
        Organization $organization,
    ): array {
        ['fragment' => $fragment, 'filename' => $filename] = $this->prepare($type, $start, $end, $userIds, $organization);

        return match ($format) {
            'excel' => [
                'filename' => "{$filename}.xlsx",
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'contents' => $this->writer->excelBytes($this->document($fragment, $type)),
            ],
            'pdf' => [
                'filename' => "{$filename}.pdf",
                'mime' => 'application/pdf',
                'contents' => $this->writer->pdfBytes($this->document($fragment, $type)),
            ],
            'word' => [
                'filename' => "{$filename}.docx",
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'contents' => $this->writer->wordBytes($fragment),
            ],
            default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * Render the report's table markup as a well-formed HTML fragment (the
     * Word writer consumes it directly; the PDF and Excel writers take it
     * wrapped in a full styled document via {@see document}) and compose the
     * download filename, pinning the locale to Spanish for the duration.
     *
     * @param  list<int>  $userIds
     * @return array{fragment: string, filename: string}
     */
    private function prepare(string $type, Carbon $start, Carbon $end, array $userIds, Organization $organization): array
    {
        $previousLocale = App::getLocale();
        App::setLocale('es');

        try {
            $fragment = View::make("exports.dt.{$type}", [
                'title' => __("ui.dt.reports.{$type}.title"),
                'report' => $this->build($type, $start, $end, $userIds),
            ])->render();

            return ['fragment' => $fragment, 'filename' => $this->filename($type, $start, $end, $organization)];
        } finally {
            App::setLocale($previousLocale);
        }
    }

    /**
     * Wrap a report fragment in the full styled HTML document the PDF and Excel
     * writers expect (Arial 8, fit-to-page — Art. 28 d, e).
     */
    private function document(string $fragment, string $type): string
    {
        return View::make('exports.dt.document', [
            'title' => __("ui.dt.reports.{$type}.title"),
            'content' => $fragment,
        ])->render();
    }

    /**
     * Build the report payload for a type, dispatching to its report service.
     *
     * @param  list<int>  $userIds
     * @return list<array<string, mixed>>
     */
    private function build(string $type, Carbon $start, Carbon $end, array $userIds): array
    {
        return match ($type) {
            'attendance' => $this->attendance->build($start, $end, $userIds),
            'daily' => $this->daily->build($start, $end, $userIds),
            'shift-changes' => $this->shiftChanges->build($start, $end, $userIds),
            'sundays' => $this->sundays->build($start, $end, $userIds),
            'incidents' => $this->incidents->build($start, $end),
            default => throw new InvalidArgumentException("Unsupported report type: {$type}"),
        };
    }

    /**
     * Compose the download file name from the report type, date range and
     * organization name, e.g. "reporte-de-asistencia_acme-spa_2026-07-01_2026-07-31".
     */
    private function filename(string $type, Carbon $start, Carbon $end, Organization $organization): string
    {
        return implode('_', [
            Str::slug(__("ui.dt.reports.types.{$type}")),
            Str::slug($organization->name),
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }
}
