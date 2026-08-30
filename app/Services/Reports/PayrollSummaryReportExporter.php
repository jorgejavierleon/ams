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
 * Turns the "Resumen de Remuneraciones por Período" report (RF-1, KOL-20)
 * into a downloadable Excel, CSV or PDF file via the shared
 * {@see ReportWriter} (KOL-15). Mirrors {@see DtReportExporter}'s design —
 * one Blade fragment renders the table once, so every format is guaranteed
 * identical to the others and to the on-screen table — without coupling
 * payroll exports to the DT-specific report registry.
 *
 * Word is not offered here: AC #4 only requires Excel, CSV and PDF.
 *
 * The report is always emitted in Spanish (AC #9), regardless of the
 * requester's chosen interface locale, matching the DT exporter's convention.
 */
class PayrollSummaryReportExporter
{
    /**
     * @var list<string>
     */
    public const FORMATS = ['excel', 'csv', 'pdf'];

    public function __construct(
        private PayrollSummaryReportBuilder $builder,
        private ReportWriter $writer,
    ) {}

    /**
     * @param  list<int>  $userIds
     */
    public function download(
        string $format,
        Carbon $start,
        Carbon $end,
        array $userIds,
        Organization $organization,
        string $delimiter = ',',
    ): Response {
        ['fragment' => $fragment, 'filename' => $filename] = $this->prepare($start, $end, $userIds, $organization);

        return match ($format) {
            'excel' => $this->writer->excel($this->document($fragment), $filename),
            'csv' => $this->writer->csv($fragment, $filename, $delimiter),
            'pdf' => $this->writer->pdf($this->document($fragment), $filename),
            default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * @param  list<int>  $userIds
     * @return array{fragment: string, filename: string}
     */
    private function prepare(Carbon $start, Carbon $end, array $userIds, Organization $organization): array
    {
        $previousLocale = App::getLocale();
        App::setLocale('es');

        try {
            $report = $this->builder->build($start, $end, $userIds);

            $fragment = View::make('exports.payroll.summary', [
                'title' => __('ui.payroll_reports.types.payroll-summary'),
                'rows' => $report['rows'],
                'total' => $report['total'],
            ])->render();

            return ['fragment' => $fragment, 'filename' => $this->filename($start, $end, $organization)];
        } finally {
            App::setLocale($previousLocale);
        }
    }

    /**
     * Wrap the report fragment in the full styled HTML document the PDF and
     * Excel writers expect. A wide column set over hundreds of employees is
     * unreadable in portrait, so {@see ReportWriter::pdf()}'s landscape
     * default is the deliberate choice here (AC #6) rather than an oversight.
     */
    private function document(string $fragment): string
    {
        return View::make('exports.payroll.document', [
            'title' => __('ui.payroll_reports.types.payroll-summary'),
            'content' => $fragment,
        ])->render();
    }

    private function filename(Carbon $start, Carbon $end, Organization $organization): string
    {
        return implode('_', [
            Str::slug(__('ui.payroll_reports.types.payroll-summary')),
            Str::slug($organization->name),
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }
}
