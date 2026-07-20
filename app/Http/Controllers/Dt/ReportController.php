<?php

namespace App\Http\Controllers\Dt;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\Premise;
use App\Models\User;
use App\Services\Reports\AttendanceReportService;
use App\Services\Reports\DailyReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entry point for the Labor Department (DT) reports section.
 *
 * Every DT report shares one filter UI — report type, date range and optional
 * employee/position/premise multi-selects — hosted here. The {@see index} action
 * renders the filter as the section landing page; the per-report actions
 * ({@see attendance}, {@see daily}, etc.) render the same page pre-selected on
 * their type and are progressively fleshed out with each report's table
 * (see issues #39–#43). All option lists are constrained to the audit session
 * organization.
 */
class ReportController extends Controller
{
    /**
     * Report types offered by the filter, in display order.
     *
     * @var list<string>
     */
    public const REPORT_TYPES = ['attendance', 'daily', 'shift-changes', 'sundays', 'incidents'];

    /**
     * Render the filter UI as the reports section landing page.
     */
    public function index(Request $request): Response
    {
        return $this->renderFilter($request, null);
    }

    /**
     * Attendance report (Resolución 38, Art. 27 a): a day-by-day attendance grid
     * per selected worker — Fecha / Asistencia / Ausencia / Observaciones — with
     * the employer, worker and place-of-service header the article requires.
     */
    public function attendance(Request $request, AttendanceReportService $service): Response
    {
        $organizationId = (int) $request->session()->get('dt_organization_id');
        $filters = $this->currentFilters($request);

        return Inertia::render('dt/reports/attendance', [
            'reportType' => 'attendance',
            'options' => $this->optionsFor($organizationId),
            'filters' => $filters,
            'report' => $service->build(
                Carbon::parse($filters['start']),
                Carbon::parse($filters['end']),
                $this->resolveWorkerIds($filters, $organizationId),
            ),
        ]);
    }

    /**
     * Daily workday report (Resolución 38, Art. 27 b): a per-worker, week-by-week
     * grid of the pacted ordinary journey and lunch against the day's marks, with
     * the shortfall ("Tiempo faltante"), overtime ("Tiempo extra") and a signed
     * weekly totals line — plus the employer/worker/place-of-service header.
     */
    public function daily(Request $request, DailyReportService $service): Response
    {
        $organizationId = (int) $request->session()->get('dt_organization_id');
        $filters = $this->currentFilters($request);

        return Inertia::render('dt/reports/daily', [
            'reportType' => 'daily',
            'options' => $this->optionsFor($organizationId),
            'filters' => $filters,
            'report' => $service->build(
                Carbon::parse($filters['start']),
                Carbon::parse($filters['end']),
                $this->resolveWorkerIds($filters, $organizationId),
            ),
        ]);
    }

    /**
     * Shift changes report (table implemented in #41).
     */
    public function shiftChanges(Request $request): Response
    {
        return $this->renderFilter($request, 'shift-changes');
    }

    /**
     * Sundays/holidays report (table implemented in #42).
     */
    public function sundays(Request $request): Response
    {
        return $this->renderFilter($request, 'sundays');
    }

    /**
     * Incidents report (table implemented in #43).
     */
    public function incidents(Request $request): Response
    {
        return $this->renderFilter($request, 'incidents');
    }

    /**
     * Render the shared filter page with the audit organization's option lists
     * and the current filter state parsed from the query string.
     */
    private function renderFilter(Request $request, ?string $reportType): Response
    {
        $organizationId = (int) $request->session()->get('dt_organization_id');

        return Inertia::render('dt/reports/index', [
            'reportType' => $reportType,
            'options' => $this->optionsFor($organizationId),
            'filters' => $this->currentFilters($request),
        ]);
    }

    /**
     * Build the shared filter option lists for the audit organization.
     *
     * @return array{
     *     employees: list<array{value: string, label: string}>,
     *     positions: list<array{value: string, label: string}>,
     *     premises: list<array{value: string, label: string}>,
     * }
     */
    private function optionsFor(int $organizationId): array
    {
        return [
            'employees' => $this->employeeOptions($organizationId),
            'positions' => $this->options(Position::query()->orderBy('name')->get()),
            'premises' => $this->options(Premise::query()->orderBy('name')->get()),
        ];
    }

    /**
     * Resolve the workers a report covers: the explicitly selected employees
     * (validated against the audit organization), or — when none are picked —
     * every employee of the organization, narrowed by the position and premise
     * filters. {@see User} carries no organization global scope, so it is
     * constrained here.
     *
     * @param  array{
     *     type: string|null,
     *     start: string,
     *     end: string,
     *     employees: list<int>,
     *     positions: list<int>,
     *     premises: list<int>,
     * }  $filters
     * @return list<int>
     */
    private function resolveWorkerIds(array $filters, int $organizationId): array
    {
        if ($filters['employees'] !== []) {
            return User::query()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $filters['employees'])
                ->pluck('id')
                ->all();
        }

        return User::query()
            ->where('organization_id', $organizationId)
            ->employees()
            ->when($filters['positions'], fn ($query, array $ids) => $query->whereIn('position_id', $ids))
            ->when($filters['premises'], fn ($query, array $ids) => $query->whereIn('premise_id', $ids))
            ->pluck('id')
            ->all();
    }

    /**
     * Build the employee select options for the audit organization.
     *
     * {@see User} is not organization-scoped by a global scope, so it is
     * constrained here to the audit session organization.
     *
     * @return list<array{value: string, label: string}>
     */
    private function employeeOptions(int $organizationId): array
    {
        return User::query()
            ->where('organization_id', $organizationId)
            ->employees()
            ->orderBy('name')
            ->get()
            ->map(fn (User $employee): array => [
                'value' => (string) $employee->id,
                'label' => $employee->formatted_rut === null
                    ? $employee->name
                    : "{$employee->name} ({$employee->formatted_rut})",
            ])
            ->all();
    }

    /**
     * Map a name-bearing model collection to `{value, label}` select options.
     *
     * @param  Collection<int, Position|Premise>  $models
     * @return list<array{value: string, label: string}>
     */
    private function options(Collection $models): array
    {
        return $models
            ->map(fn (Position|Premise $model): array => [
                'value' => (string) $model->id,
                'label' => $model->name,
            ])
            ->all();
    }

    /**
     * Parse the current filter state from the query string, defaulting the date
     * range to the current month.
     *
     * @return array{
     *     type: string|null,
     *     start: string,
     *     end: string,
     *     employees: list<int>,
     *     positions: list<int>,
     *     premises: list<int>,
     * }
     */
    private function currentFilters(Request $request): array
    {
        $start = $request->date('start') ?? Carbon::now()->startOfMonth();
        $end = $request->date('end') ?? Carbon::now()->endOfMonth();

        return [
            'type' => $request->string('type')->toString() ?: null,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'employees' => $this->intIds($request->input('employees', [])),
            'positions' => $this->intIds($request->input('positions', [])),
            'premises' => $this->intIds($request->input('premises', [])),
        ];
    }

    /**
     * Normalise a query-string id list into a list of unique integers.
     *
     * @return list<int>
     */
    private function intIds(mixed $value): array
    {
        return collect((array) $value)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
