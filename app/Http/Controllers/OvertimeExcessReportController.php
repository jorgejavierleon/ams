<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesReportEmployeeFilters;
use App\Concerns\ResolvesTableSort;
use App\Enums\ReportPeriodType;
use App\Models\Organization;
use App\Models\User;
use App\Services\Overtime\OvertimePayBucketClassifier;
use App\Services\Reports\OvertimeExcessReportBuilder;
use App\Services\Reports\OvertimeExcessReportExporter;
use App\Services\Reports\PayrollExportFinding;
use App\Services\Reports\PayrollExportReadiness;
use App\Services\Reports\PayrollExportReadinessService;
use App\Services\Reports\ReportEmployeeSelector;
use App\Support\CurrentOrganization;
use App\Support\EmployeeSelection;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * "Excesos de Jornada y HHEE" (RF-1, KOL-24): overtime grouped by week, split
 * into pactada (authorised, per pay bucket) and no pactada (unauthorised),
 * for a single employee or a consolidated selection — one row per employee
 * per week, the same shape either way (unlike
 * {@see WeeklyDetailReportController}'s exactly-one restriction). Every
 * figure comes from {@see OvertimeExcessReportBuilder}, which itself only
 * formats what {@see OvertimePayBucketClassifier}
 * (KOL-12) already classified; this controller never calculates a figure.
 */
class OvertimeExcessReportController extends Controller
{
    use ResolvesReportEmployeeFilters, ResolvesTableSort;

    public function index(
        Request $request,
        ReportEmployeeSelector $selector,
        OvertimeExcessReportBuilder $builder,
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
        $readiness = $readinessService->check($period->start(), $period->end(), $userIds);

        return Inertia::render('payroll-reports/overtime-excess', [
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
            'weeks' => $report['weeks'],
            'readiness' => $this->readinessProp($readiness, $userIds),
        ]);
    }

    public function export(
        Request $request,
        string $format,
        ReportEmployeeSelector $selector,
        PayrollExportReadinessService $readinessService,
        OvertimeExcessReportExporter $exporter,
    ): HttpResponse {
        abort_unless(in_array($format, OvertimeExcessReportExporter::FORMATS, true), 404);

        $period = $this->resolvePeriod($request);
        $filters = $this->reportEmployeeFilters($request);
        $selection = $this->resolveSelection($request);
        $userIds = $selector->resolve($filters, $selection);
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
            'overtime-excess',
            $period->start(),
            $period->end(),
            $format,
            $userIds,
            $readiness,
            $confirmed,
            [...$this->reportEmployeeFiltersProp($filters), 'select_all' => $selection->selectAll],
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
