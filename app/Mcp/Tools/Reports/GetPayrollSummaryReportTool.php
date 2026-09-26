<?php

namespace App\Mcp\Tools\Reports;

use App\Enums\ReportPeriodType;
use App\Http\Controllers\PayrollSummaryReportController;
use App\Mcp\Tools\AuthorizedTool;
use App\Models\Organization;
use App\Models\User;
use App\Services\Reports\PayrollExportFinding;
use App\Services\Reports\PayrollExportReadiness;
use App\Services\Reports\PayrollExportReadinessService;
use App\Services\Reports\PayrollSummaryReportExporter;
use App\Services\Reports\ReportEmployeeSelector;
use App\Support\CurrentOrganization;
use App\Support\EmployeeSelection;
use App\Support\ReportEmployeeFilters;
use App\Support\ReportPeriod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Fetches the "Resumen de Remuneraciones por Período" report (RF-1, KOL-20)
 * as CSV text, for an agent to hand off to an accountant or payroll system,
 * exactly as {@see PayrollSummaryReportController::export()}
 * does for the "csv" format — same builder, same
 * {@see PayrollSummaryReportExporter}, same `;` delimiter, so the figures
 * always match the web export.
 *
 * Unlike the web export, this tool never blocks on
 * {@see PayrollExportReadinessService}'s findings and needs no confirmation
 * step: it always returns the requested data, adding a `warning` alongside
 * it when the period has unresolved findings, so the calling agent can
 * relay that context to whoever asked for the report.
 *
 * Every call is still recorded via
 * {@see PayrollExportReadinessService::recordExport()}, the same
 * `payroll_export` activity log the web export writes to (KOL-17), so the
 * browsable export history has no blind spot for MCP-driven exports.
 * `confirmed` is always `false` here — there is no confirmation step to
 * report on.
 */
#[Name('get-payroll-summary-report')]
#[Description('Fetch the payroll summary report ("Resumen de Remuneraciones por Período") as CSV text for a period, optionally limited to specific employees. Always returns the report; when the period has unresolved attendance findings, includes them as a warning alongside the data instead of refusing.')]
class GetPayrollSummaryReportTool extends AuthorizedTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period_year' => $schema->integer()
                ->description('The period\'s year, e.g. 2026.')
                ->required(),
            'period_month' => $schema->integer()
                ->description('The period\'s month, 1-12.')
                ->required(),
            'period_type' => $schema->string()
                ->enum(array_map(fn (ReportPeriodType $type): string => $type->value, ReportPeriodType::cases()))
                ->description('The pay-period shape: "month" (the whole month), "first_fortnight" (1st-15th) or "second_fortnight" (16th-end). Defaults to "month".'),
            'employee_ids' => $schema->array()
                ->items($schema->integer())
                ->description('Specific employee ids to include. Omit for every current employee in your organization.'),
        ];
    }

    public function handle(
        Request $request,
        ReportEmployeeSelector $selector,
        PayrollExportReadinessService $readinessService,
        PayrollSummaryReportExporter $exporter,
    ): Response|ResponseFactory {
        if ($response = $this->authorize($request, 'Export:PayrollReport')) {
            return $response;
        }

        $data = $request->validate([
            'period_year' => ['required', 'integer'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_type' => ['nullable', Rule::enum(ReportPeriodType::class)],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
        ]);

        $period = new ReportPeriod(
            year: $data['period_year'],
            month: $data['period_month'],
            type: isset($data['period_type']) ? ReportPeriodType::from($data['period_type']) : ReportPeriodType::Month,
        );

        $employeeIds = $data['employee_ids'] ?? [];
        $selection = $employeeIds === []
            ? new EmployeeSelection(selectAll: true)
            : new EmployeeSelection(selectAll: false, ids: $employeeIds);

        $userIds = $selector->resolve(new ReportEmployeeFilters, $selection);

        $readiness = $readinessService->check($period->start(), $period->end(), $userIds);

        $csv = $exporter->csvText(
            $period->start(),
            $period->end(),
            $userIds,
            Organization::findOrFail(CurrentOrganization::id()),
        );

        /** @var User $user */
        $user = $request->user();

        $readinessService->recordExport(
            $user,
            'payroll-summary',
            $period->start(),
            $period->end(),
            'csv',
            $userIds,
            $readiness,
            confirmed: false,
            filters: ['employee_ids' => $employeeIds, 'select_all' => $employeeIds === []],
        );

        $result = ['csv' => $csv];

        if (! $readiness->isClean()) {
            $result['warning'] = $this->warning($readiness, $userIds);
        }

        return Response::structured($result);
    }

    /**
     * @param  list<int>  $userIds
     * @return array{message: string, findings: array<int, array{type: string, employee_id: int|null, employee_name: string|null, date: string|null, reason: string}>}
     */
    private function warning(PayrollExportReadiness $readiness, array $userIds): array
    {
        /** @var Collection<int, string> $namesById */
        $namesById = $userIds === []
            ? collect()
            : User::query()
                ->where('organization_id', CurrentOrganization::id())
                ->whereIn('id', $userIds)
                ->pluck('name', 'id');

        return [
            'message' => 'This period has unresolved attendance findings. The report data below is unaffected, but relay these to whoever asked for the report.',
            'findings' => $readiness->findings
                ->map(fn (PayrollExportFinding $finding): array => [
                    'type' => $finding->type->value,
                    'employee_id' => $finding->userId,
                    'employee_name' => $finding->userId === null ? null : $namesById->get($finding->userId),
                    'date' => $finding->date?->format('Y-m-d'),
                    'reason' => $finding->reason,
                ])
                ->values()
                ->all(),
        ];
    }
}
