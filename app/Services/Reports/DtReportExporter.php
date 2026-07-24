<?php

namespace App\Services\Reports;

use App\Models\Organization;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlSpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html as WordHtml;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns any of the five Resolución 38 reports into a downloadable Excel, PDF or
 * Word file (Art. 28 b). Each report is rendered once to an HTML table by its
 * Blade view — the single source that also drives the on-screen React table — so
 * every format is identical to the others and to the screen (Art. 28 a). The
 * three writers apply Arial 8 (Art. 28 e): the Blade styles cover the PDF while
 * the spreadsheet and document default fonts are set here.
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
        $previousLocale = App::getLocale();
        App::setLocale('es');

        try {
            // The report's table markup, as a well-formed HTML fragment. The Word
            // writer consumes it directly; the PDF and Excel writers take it
            // wrapped in a full styled document.
            $fragment = View::make("exports.dt.{$type}", [
                'title' => __("ui.dt.reports.{$type}.title"),
                'report' => $this->build($type, $start, $end, $userIds),
            ])->render();

            $filename = $this->filename($type, $start, $end, $organization);
        } finally {
            App::setLocale($previousLocale);
        }

        return match ($format) {
            'excel' => $this->excel($this->document($fragment, $type), $filename),
            'pdf' => Pdf::loadHTML($this->document($fragment, $type))
                ->setPaper('letter', 'landscape')
                ->download("{$filename}.pdf"),
            'word' => $this->word($fragment, $filename),
            default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
        };
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
     * Render the report HTML into a real .xlsx workbook. PhpSpreadsheet's HTML
     * reader interprets the same table markup the PDF uses, and the default font
     * is forced to Arial 8 (Art. 28 e).
     */
    private function excel(string $html, string $filename): StreamedResponse
    {
        $spreadsheet = (new HtmlSpreadsheetReader)->loadFromString($html);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(8);

        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            "{$filename}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Render the report fragment into a real .docx document. PhpWord's HTML
     * reader parses with a strict XML parser, so it is fed the well-formed
     * fragment (no document shell); the default font is forced to Arial 8
     * (Art. 28 e) and the page laid out landscape to fit the wide tables
     * (Art. 28 d).
     */
    private function word(string $fragment, string $filename): StreamedResponse
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(8);

        $section = $phpWord->addSection(['orientation' => 'landscape']);
        WordHtml::addHtml($section, $fragment, false, false);

        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            "{$filename}.docx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
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
