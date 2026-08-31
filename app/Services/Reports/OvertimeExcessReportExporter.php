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
 * Turns the "Excesos de Jornada y HHEE" report (RF-1, KOL-24) into a
 * downloadable Excel or PDF file via the shared {@see ReportWriter} (KOL-15).
 * Mirrors {@see WeeklyDetailReportExporter}: one Blade fragment renders the
 * table once, so the file is guaranteed identical to the on-screen report.
 *
 * CSV is not offered here — RF-1's table lists only Excel and PDF for this
 * report (AC #6).
 *
 * Always rendered in Spanish (AC #8), regardless of the requester's chosen
 * interface locale, matching the other payroll exporters' convention.
 */
class OvertimeExcessReportExporter
{
    /**
     * @var list<string>
     */
    public const FORMATS = ['excel', 'pdf'];

    public function __construct(
        private OvertimeExcessReportBuilder $builder,
        private ReportWriter $writer,
    ) {}

    /**
     * @param  list<int>  $userIds
     */
    public function download(string $format, Carbon $start, Carbon $end, array $userIds, Organization $organization): Response
    {
        ['fragment' => $fragment, 'filename' => $filename] = $this->prepare($start, $end, $userIds, $organization);

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
    private function prepare(Carbon $start, Carbon $end, array $userIds, Organization $organization): array
    {
        $previousLocale = App::getLocale();
        App::setLocale('es');

        try {
            $report = $this->builder->build($start, $end, $userIds);

            $fragment = View::make('exports.payroll.overtime-excess', [
                'title' => __('ui.payroll_reports.types.overtime-excess'),
                'weeks' => $report['weeks'],
            ])->render();

            return ['fragment' => $fragment, 'filename' => $this->filename($start, $end, $organization)];
        } finally {
            App::setLocale($previousLocale);
        }
    }

    /**
     * Wrap the report fragment in the full styled HTML document the PDF and
     * Excel writers expect — the same shell every payroll export uses.
     */
    private function document(string $fragment): string
    {
        return View::make('exports.payroll.document', [
            'title' => __('ui.payroll_reports.types.overtime-excess'),
            'content' => $fragment,
        ])->render();
    }

    private function filename(Carbon $start, Carbon $end, Organization $organization): string
    {
        return implode('_', [
            Str::slug(__('ui.payroll_reports.types.overtime-excess')),
            Str::slug($organization->name),
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }
}
