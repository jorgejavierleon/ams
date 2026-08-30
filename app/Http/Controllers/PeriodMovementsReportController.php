<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesReportEmployeeFilters;
use App\Concerns\ResolvesTableSort;
use App\Enums\ReportPeriodType;
use App\Models\Organization;
use App\Models\User;
use App\Services\Reports\PayrollExportReadiness;
use App\Services\Reports\PayrollExportReadinessService;
use App\Services\Reports\PeriodMovementsReportBuilder;
use App\Services\Reports\PeriodMovementsReportExporter;
use App\Services\Reports\ReportEmployeeSelector;
use App\Support\CurrentOrganization;
use App\Support\EmployeeSelection;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * "Movimientos del Período" (RF-1, KOL-22) — altas, bajas, licencia starts
 * and ends, approved vacations and shift changes for the selected
 * employees/period, grouped by movement type. Every figure comes from
 * {@see PeriodMovementsReportBuilder}; this controller never calculates one.
 *
 * Unlike {@see PayrollSummaryReportController}, there is no payroll-figure
 * integrity check here (KOL-14 targets hours/attendance, not the movement
 * types this report lists) — only the export audit trail every payroll
 * report writes (AC #7), via the same {@see PayrollExportReadinessService}
 * so a future browsable history reads one log for every report.
 */
class PeriodMovementsReportController extends Controller
{
    use ResolvesReportEmployeeFilters, ResolvesTableSort;

    public function index(
        Request $request,
        ReportEmployeeSelector $selector,
        PeriodMovementsReportBuilder $builder,
    ): Response {
        $period = $this->resolvePeriod($request);
        $filters = $this->reportEmployeeFilters($request);
        $selection = $this->resolveSelection($request);
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort($request, ['name', 'email'], 'name');

        $employees = $selector->paginate($filters, $search, $sort, $direction, perPage: 40);
        $userIds = $selector->resolve($filters, $selection);

        $report = $builder->build($period->start(), $period->end(), $userIds);

        return Inertia::render('payroll-reports/period-movements', [
            'period' => ['year' => $period->year, 'month' => $period->month, 'type' => $period->type->value],
            'selection' => ['selectAll' => $selection->selectAll, 'ids' => $selection->ids],
            'selectedEmployeeCount' => count($userIds),
            'employees' => $employees->through(fn (User $employee): array => $this->payrollReportEmployeeRow($employee)),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                ...$this->reportEmployeeFiltersProp($filters),
            ],
            'filterOptions' => $selector->optionsFor(),
            'movements' => $report,
        ]);
    }

    public function export(
        Request $request,
        string $format,
        ReportEmployeeSelector $selector,
        PayrollExportReadinessService $readinessService,
        PeriodMovementsReportExporter $exporter,
    ): HttpResponse {
        abort_unless(in_array($format, PeriodMovementsReportExporter::FORMATS, true), 404);

        $period = $this->resolvePeriod($request);
        $filters = $this->reportEmployeeFilters($request);
        $selection = $this->resolveSelection($request);
        $userIds = $selector->resolve($filters, $selection);

        $readinessService->recordExport(
            $request->user(),
            'period-movements',
            $period->start(),
            $period->end(),
            $format,
            $userIds,
            new PayrollExportReadiness(collect()),
            confirmed: true,
        );

        return $exporter->download(
            $format,
            $period->start(),
            $period->end(),
            $userIds,
            Organization::findOrFail(CurrentOrganization::id()),
        );
    }

    private function resolvePeriod(Request $request): ReportPeriod
    {
        $today = now();
        $type = ReportPeriodType::tryFrom((string) $request->input('period_type')) ?? ReportPeriodType::Month;

        return new ReportPeriod(
            year: (int) ($request->input('period_year') ?: $today->year),
            month: (int) ($request->input('period_month') ?: $today->month),
            type: $type,
        );
    }

    private function resolveSelection(Request $request): EmployeeSelection
    {
        return new EmployeeSelection(
            selectAll: $request->boolean('selectAll'),
            ids: $this->idListFilter($request, 'ids'),
        );
    }
}
