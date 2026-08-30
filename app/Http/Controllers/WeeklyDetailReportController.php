<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesReportEmployeeFilters;
use App\Concerns\ResolvesTableSort;
use App\Enums\ReportPeriodType;
use App\Models\User;
use App\Services\Reports\PayrollExportFinding;
use App\Services\Reports\PayrollExportReadiness;
use App\Services\Reports\PayrollExportReadinessService;
use App\Services\Reports\ReportEmployeeSelector;
use App\Services\Reports\WeeklyDetailReportBuilder;
use App\Services\Reports\WeeklyDetailReportExporter;
use App\Support\CurrentOrganization;
use App\Support\EmployeeSelection;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * "Detalle Semanal por Trabajador" (RF-1, KOL-21) — Talana's "Reporte Semanal
 * Persona" equivalent: real versus theoretical entrada/salida/colación,
 * day by day, for one worker at a time. Every figure comes from
 * {@see WeeklyDetailReportBuilder}, which only formats what `workdays`
 * already stores; this controller never calculates a figure.
 *
 * Unlike {@see PayrollSummaryReportController} (one row per employee), this
 * report is individual level: it reuses the exact same shared filter and
 * selection (KOL-19) so every RF-1 report behaves identically, but only
 * renders a report body once that selection resolves to exactly one
 * employee — {@see self::index()} still shows the picker either way so the
 * user can narrow down to the one worker they need.
 */
class WeeklyDetailReportController extends Controller
{
    use ResolvesReportEmployeeFilters, ResolvesTableSort;

    public function index(
        Request $request,
        ReportEmployeeSelector $selector,
        WeeklyDetailReportBuilder $builder,
        PayrollExportReadinessService $readinessService,
    ): Response {
        $period = $this->resolvePeriod($request);
        $filters = $this->reportEmployeeFilters($request);
        $selection = $this->resolveSelection($request);
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort($request, ['name', 'email'], 'name');

        $employees = $selector->paginate($filters, $search, $sort, $direction, perPage: 40);
        $userIds = $selector->resolve($filters, $selection);

        $report = $builder->build($period->start(), $period->end(), $userIds);
        $readiness = count($userIds) === 1
            ? $readinessService->check($period->start(), $period->end(), $userIds)
            : null;

        return Inertia::render('payroll-reports/weekly-detail', [
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
            'employee' => $report['employee'],
            'weeks' => $report['weeks'],
            'readiness' => $readiness === null ? null : $this->readinessProp($readiness, $userIds),
        ]);
    }

    public function export(
        Request $request,
        string $format,
        ReportEmployeeSelector $selector,
        PayrollExportReadinessService $readinessService,
        WeeklyDetailReportExporter $exporter,
    ): HttpResponse {
        abort_unless(in_array($format, WeeklyDetailReportExporter::FORMATS, true), 404);

        $period = $this->resolvePeriod($request);
        $filters = $this->reportEmployeeFilters($request);
        $selection = $this->resolveSelection($request);
        $userIds = $selector->resolve($filters, $selection);

        if (count($userIds) !== 1) {
            return response()->json([
                'message' => __('ui.payroll_reports.weekly_detail.select_one_required'),
            ], 422);
        }

        $confirmed = $request->boolean('confirmed');

        $readiness = $readinessService->check($period->start(), $period->end(), $userIds);

        if ($readiness->requiresConfirmation() && ! $confirmed) {
            return response()->json([
                'message' => __('ui.payroll_reports.summary.export.confirm_required'),
            ], 422);
        }

        $user = $request->user();

        if ($readiness->requiresConfirmation() && $confirmed) {
            $readinessService->recordConfirmation($user, $period->start(), $period->end(), $readiness);
        }

        $readinessService->recordExport(
            $user,
            'weekly-detail',
            $period->start(),
            $period->end(),
            $format,
            $userIds,
            $readiness,
            $confirmed,
        );

        return $exporter->download($format, $period->start(), $period->end(), $userIds);
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

    /**
     * @param  list<int>  $userIds
     * @return array{
     *     findings: list<array{type: string, employee_id: int|null, employee_name: string|null, date: string|null, reason: string, resolution_url: string|null, blocking: bool}>,
     *     isClean: bool,
     *     requiresConfirmation: bool,
     * }
     */
    private function readinessProp(PayrollExportReadiness $readiness, array $userIds): array
    {
        $namesById = $userIds === []
            ? collect()
            : User::query()
                ->where('organization_id', CurrentOrganization::id())
                ->whereIn('id', $userIds)
                ->pluck('name', 'id');

        return [
            'findings' => array_values($readiness->findings
                ->map(fn (PayrollExportFinding $finding): array => [
                    'type' => $finding->type->value,
                    'employee_id' => $finding->userId,
                    'employee_name' => $finding->userId === null ? null : ($namesById[$finding->userId] ?? null),
                    'date' => $finding->date?->format('d-m-Y'),
                    'reason' => $finding->reason,
                    'resolution_url' => $finding->resolutionUrl,
                    'blocking' => $finding->type->blocking(),
                ])
                ->all()),
            'isClean' => $readiness->isClean(),
            'requiresConfirmation' => $readiness->requiresConfirmation(),
        ];
    }
}
