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
 * Turns "Movimientos del Período" (RF-1, KOL-22) into a downloadable
 * multi-sheet Excel workbook via the shared {@see ReportWriter} (KOL-15) —
 * one named sheet per movement type (AC #2), each rendered from its own
 * Blade fragment so the file matches the on-screen tabs exactly. The shift
 * changes sheet reuses `exports.dt.shift-changes` verbatim (AC #5), the same
 * fragment the DT Art. 27 d) report renders.
 *
 * Excel is the only format: per the PRD's report table, this report is
 * Excel-only (multi-hoja por tipo), unlike the other RF-1 reports.
 *
 * Always rendered in Spanish (AC #8), regardless of the requester's chosen
 * interface locale, matching every other payroll/DT exporter's convention.
 */
class PeriodMovementsReportExporter
{
    /**
     * @var list<string>
     */
    public const FORMATS = ['excel'];

    public function __construct(
        private PeriodMovementsReportBuilder $builder,
        private ReportWriter $writer,
    ) {}

    /**
     * @param  list<int>  $userIds
     */
    public function download(string $format, Carbon $start, Carbon $end, array $userIds, Organization $organization): Response
    {
        if ($format !== 'excel') {
            throw new InvalidArgumentException("Unsupported export format: {$format}");
        }

        $previousLocale = App::getLocale();
        App::setLocale('es');

        try {
            $report = $this->builder->build($start, $end, $userIds);

            $sheets = [
                __('ui.payroll_reports.movements.tabs.hires') => View::make('exports.payroll.movements.hires', [
                    'rows' => $report['hires'],
                ])->render(),
                __('ui.payroll_reports.movements.tabs.terminations') => View::make('exports.payroll.movements.terminations', [
                    'rows' => $report['terminations'],
                ])->render(),
                __('ui.payroll_reports.movements.tabs.leave_starts') => View::make('exports.payroll.movements.leave-movements', [
                    'rows' => $report['leaveStarts'],
                    'emptyMessage' => __('ui.payroll_reports.movements.empty.leave_starts'),
                ])->render(),
                __('ui.payroll_reports.movements.tabs.leave_ends') => View::make('exports.payroll.movements.leave-movements', [
                    'rows' => $report['leaveEnds'],
                    'emptyMessage' => __('ui.payroll_reports.movements.empty.leave_ends'),
                ])->render(),
                __('ui.payroll_reports.movements.tabs.vacations') => View::make('exports.payroll.movements.leave-movements', [
                    'rows' => $report['vacations'],
                    'emptyMessage' => __('ui.payroll_reports.movements.empty.vacations'),
                ])->render(),
                __('ui.payroll_reports.movements.tabs.shift_changes') => View::make('exports.dt.shift-changes', [
                    'title' => __('ui.payroll_reports.movements.tabs.shift_changes'),
                    'report' => $report['shiftChanges'],
                ])->render(),
            ];

            return $this->writer->excelSheets($sheets, $this->filename($start, $end, $organization));
        } finally {
            App::setLocale($previousLocale);
        }
    }

    private function filename(Carbon $start, Carbon $end, Organization $organization): string
    {
        return implode('_', [
            Str::slug(__('ui.payroll_reports.types.period-movements')),
            Str::slug($organization->name),
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }
}
