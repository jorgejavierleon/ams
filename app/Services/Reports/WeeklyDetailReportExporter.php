<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the "Detalle Semanal por Trabajador" report (RF-1, KOL-21) into a
 * downloadable Excel or PDF file via the shared {@see ReportWriter} (KOL-15).
 * Mirrors {@see PayrollSummaryReportExporter}: one Blade fragment renders the
 * table once, so the file is guaranteed identical to the on-screen report.
 *
 * CSV is not offered here — RF-1's table lists only Excel and PDF for this
 * report (AC #5).
 *
 * Always rendered in Spanish (AC #8), regardless of the requester's chosen
 * interface locale, matching the DT and payroll-summary exporters' convention.
 */
class WeeklyDetailReportExporter
{
    /**
     * @var list<string>
     */
    public const FORMATS = ['excel', 'pdf'];

    public function __construct(
        private WeeklyDetailReportBuilder $builder,
        private ReportWriter $writer,
    ) {}

    /**
     * @param  list<int>  $userIds
     */
    public function download(string $format, Carbon $start, Carbon $end, array $userIds): Response
    {
        ['fragment' => $fragment, 'filename' => $filename] = $this->prepare($start, $end, $userIds);

        return match ($format) {
            'excel' => $this->writer->excel($this->document($fragment), $filename),
            'pdf' => $this->writer->pdf($this->document($fragment), $filename),
            default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * @param  list<int>  $userIds
     * @return array{fragment: string, filename: string}
     */
    private function prepare(Carbon $start, Carbon $end, array $userIds): array
    {
        $previousLocale = App::getLocale();
        App::setLocale('es');

        try {
            $report = $this->builder->build($start, $end, $userIds);

            $fragment = View::make('exports.payroll.weekly-detail', [
                'title' => __('ui.payroll_reports.types.weekly-detail'),
                'employee' => $report['employee'],
                'weeks' => $report['weeks'],
            ])->render();

            return ['fragment' => $fragment, 'filename' => $this->filename($start, $end, $report['employee'])];
        } finally {
            App::setLocale($previousLocale);
        }
    }

    /**
     * Wrap the report fragment in the full styled HTML document the PDF and
     * Excel writers expect — the same shell {@see PayrollSummaryReportExporter}
     * uses, so every payroll export looks consistent.
     */
    private function document(string $fragment): string
    {
        return View::make('exports.payroll.document', [
            'title' => __('ui.payroll_reports.types.weekly-detail'),
            'content' => $fragment,
        ])->render();
    }

    /**
     * @param  array{id: int, name: string, rut: string|null}|null  $employee
     */
    private function filename(Carbon $start, Carbon $end, ?array $employee): string
    {
        return implode('_', array_filter([
            Str::slug(__('ui.payroll_reports.types.weekly-detail')),
            $employee === null ? null : Str::slug($employee['name']),
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]));
    }
}
