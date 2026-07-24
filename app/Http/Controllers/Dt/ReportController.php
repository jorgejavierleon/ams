<?php

namespace App\Http\Controllers\Dt;

use App\Enums\ShiftType;
use App\Http\Controllers\Controller;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Premise;
use App\Models\Shift;
use App\Models\User;
use App\Services\Reports\AttendanceReportService;
use App\Services\Reports\DailyReportService;
use App\Services\Reports\DtReportExporter;
use App\Services\Reports\IncidentsReportService;
use App\Services\Reports\ShiftChangesReportService;
use App\Services\Reports\SundaysReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
    public const REPORT_TYPES = ['attendance', 'daily', 'sundays', 'shift-changes', 'incidents'];

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
     * Shift changes report (Resolución 38, Art. 27 d): per worker, every shift
     * change taking effect in the range — previous and new shift with their
     * detail, extension and dates, who requested it — or the legend justifying
     * the absence of changes for workers on a fixed permanent journey.
     */
    public function shiftChanges(Request $request, ShiftChangesReportService $service): Response
    {
        $organizationId = (int) $request->session()->get('dt_organization_id');
        $filters = $this->currentFilters($request);

        return Inertia::render('dt/reports/shift-changes', [
            'reportType' => 'shift-changes',
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
     * Sundays/holidays report (Resolución 38, Art. 27 c): per worker, every
     * Sunday and public holiday in the range on which the worker worked or was
     * rostered — with the retail additional-Sunday flag, "Asistencia",
     * "Ausencia" and "Observaciones" columns, per-month subtotals of days
     * worked and a final total — or the fixed-journey legend for workers whose
     * journey never falls on such days.
     */
    public function sundays(Request $request, SundaysReportService $service): Response
    {
        $organizationId = (int) $request->session()->get('dt_organization_id');
        $filters = $this->currentFilters($request);

        return Inertia::render('dt/reports/sundays', [
            'reportType' => 'sundays',
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
     * Technical incidents report (Resolución 38, Art. 27 f): the audited
     * employer's log of attendance-system outages — start, end, duration and
     * description — narrowed to the selected date range. It is a per-employer
     * log, so (per Art. 24 d) it takes no worker filter.
     */
    public function incidents(Request $request, IncidentsReportService $service): Response
    {
        $filters = $this->currentFilters($request);

        return Inertia::render('dt/reports/incidents', [
            'reportType' => 'incidents',
            'options' => $this->optionsFor((int) $request->session()->get('dt_organization_id')),
            'filters' => $filters,
            'report' => $service->build(
                Carbon::parse($filters['start']),
                Carbon::parse($filters['end']),
            ),
        ]);
    }

    /**
     * Stream any report as an Excel, PDF or Word download (Resolución 38,
     * Art. 28 b). The report type comes from the route and the format from the
     * `format` query param; the same filter and worker-resolution logic that
     * drives the on-screen tables selects the rows, so the download matches the
     * screen (Art. 28 a). The incidents log is per-employer (Art. 24 d) and takes
     * no worker filter.
     */
    public function export(Request $request, DtReportExporter $exporter, string $type): HttpResponse
    {
        $format = $request->string('format')->toString();

        abort_unless(in_array($type, self::REPORT_TYPES, true), 404);
        abort_unless(in_array($format, DtReportExporter::FORMATS, true), 404);

        $organizationId = (int) $request->session()->get('dt_organization_id');
        $filters = $this->currentFilters($request);

        return $exporter->download(
            $type,
            $format,
            Carbon::parse($filters['start']),
            Carbon::parse($filters['end']),
            $type === 'incidents' ? [] : $this->resolveWorkerIds($filters, $organizationId),
            Organization::findOrFail($organizationId),
        );
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
     *     employees: list<array{value: string, label: string, keywords: list<string>}>,
     *     positions: list<array{value: string, label: string}>,
     *     premises: list<array{value: string, label: string}>,
     *     shifts: list<array{value: string, label: string}>,
     *     journals: list<array{value: string, label: string}>,
     * }
     */
    private function optionsFor(int $organizationId): array
    {
        return [
            'employees' => $this->employeeOptions($organizationId),
            'positions' => $this->options(Position::query()->orderBy('name')->get()),
            'premises' => $this->options(Premise::query()->orderBy('name')->get()),
            'shifts' => $this->shiftOptions(),
            'journals' => ShiftType::options(),
        ];
    }

    /**
     * Build the shift ("Turnos") select options for the audit organization,
     * labelled by schedule extension rather than name (Resolución 38,
     * Art. 25.1.f). {@see Shift} is organization-scoped, so it is already
     * constrained to the audit session organization.
     *
     * @return list<array{value: string, label: string}>
     */
    private function shiftOptions(): array
    {
        return array_values(
            Shift::query()
                ->with('days')
                ->orderBy('name')
                ->get()
                ->map(fn (Shift $shift): array => [
                    'value' => (string) $shift->id,
                    'label' => $shift->extensionLabel(),
                ])
                ->all()
        );
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
     *     journals: list<string>,
     *     shifts: list<int>,
     *     checksum: string|null,
     * }  $filters
     * @return list<int>
     */
    private function resolveWorkerIds(array $filters, int $organizationId): array
    {
        // A checksum pins the report to the single worker who owns the matching
        // mark (Resolución 38, Art. 25.1.j); it overrides the broader filters.
        if ($filters['checksum'] !== null) {
            return $this->workerIdsForChecksum($filters['checksum'], $organizationId);
        }

        $query = User::query()->where('organization_id', $organizationId);

        if ($filters['employees'] !== []) {
            $query->whereIn('id', $filters['employees']);
        } else {
            $query->employees()
                ->when($filters['positions'], fn ($query, array $ids) => $query->whereIn('position_id', $ids))
                ->when($filters['premises'], fn ($query, array $ids) => $query->whereIn('premise_id', $ids));
        }

        // Jornada (Art. 25.1.e) and Turnos (Art. 25.1.f): keep only workers with
        // a shift assignment overlapping the report range whose shift matches the
        // chosen types / shifts. Both narrow simultaneously (Art. 25.2).
        $start = Carbon::parse($filters['start']);
        $end = Carbon::parse($filters['end']);

        $overlapsRange = fn ($assignment) => $assignment
            ->whereDate('start_date', '<=', $end)
            ->where(fn ($range) => $range
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $start));

        if ($filters['journals'] !== []) {
            $query->whereHas('shiftAssignments', fn ($assignment) => $overlapsRange($assignment)
                ->whereHas('shift', fn ($shift) => $shift->whereIn('type', $filters['journals'])));
        }

        if ($filters['shifts'] !== []) {
            $query->whereHas('shiftAssignments', fn ($assignment) => $overlapsRange($assignment)
                ->whereIn('shift_id', $filters['shifts']));
        }

        return array_values($query->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * Resolve the worker owning the mark carrying the given checksum, constrained
     * to the audit organization. Returns an empty list when no mark matches.
     *
     * @return list<int>
     */
    private function workerIdsForChecksum(string $checksum, int $organizationId): array
    {
        $userId = Mark::query()
            ->where('checksum', $checksum)
            ->value('user_id');

        if ($userId === null) {
            return [];
        }

        return array_values(
            User::query()
                ->where('organization_id', $organizationId)
                ->whereKey($userId)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
        );
    }

    /**
     * Build the employee select options for the audit organization.
     *
     * {@see User} is not organization-scoped by a global scope, so it is
     * constrained here to the audit session organization.
     *
     * The label keeps the dotted RUT for display, but the search must also match
     * the RUT "sin puntos y con guión" the inspector types (Resolución 38,
     * Art. 25.1.a) — the canonical stored form (`12345678-5`) — plus the bare
     * digits for convenience. Both go into `keywords` rather than the label.
     *
     * @return list<array{value: string, label: string, keywords: list<string>}>
     */
    private function employeeOptions(int $organizationId): array
    {
        return array_values(
            User::query()
                ->where('organization_id', $organizationId)
                ->employees()
                ->orderBy('name')
                ->get()
                ->map(fn (User $employee): array => [
                    'value' => (string) $employee->id,
                    'label' => $employee->formatted_rut === null
                        ? $employee->name
                        : "{$employee->name} ({$employee->formatted_rut})",
                    'keywords' => $employee->rut === null
                        ? []
                        : [$employee->rut, str_replace('-', '', $employee->rut)],
                ])
                ->all()
        );
    }

    /**
     * Map a name-bearing model collection to `{value, label}` select options.
     *
     * @template TModel of Position|Premise
     *
     * @param  Collection<int, TModel>  $models
     * @return list<array{value: string, label: string}>
     */
    private function options(Collection $models): array
    {
        return array_values(
            $models
                ->map(fn (Position|Premise $model): array => [
                    'value' => (string) $model->id,
                    'label' => $model->name,
                ])
                ->all()
        );
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
     *     journals: list<string>,
     *     shifts: list<int>,
     *     checksum: string|null,
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
            'journals' => $this->journalValues($request->input('journals', [])),
            'shifts' => $this->intIds($request->input('shifts', [])),
            'checksum' => $request->string('checksum')->trim()->toString() ?: null,
        ];
    }

    /**
     * Normalise the jornada filter into a list of valid {@see ShiftType} values,
     * discarding anything that is not a real shift type.
     *
     * @return list<string>
     */
    private function journalValues(mixed $value): array
    {
        $valid = array_map(fn (ShiftType $type): string => $type->value, ShiftType::cases());

        return array_values(
            collect((array) $value)
                ->map(fn ($type): string => (string) $type)
                ->filter(fn (string $type): bool => in_array($type, $valid, true))
                ->unique()
                ->all()
        );
    }

    /**
     * Normalise a query-string id list into a list of unique integers.
     *
     * @return list<int>
     */
    private function intIds(mixed $value): array
    {
        return array_values(
            collect((array) $value)
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->all()
        );
    }
}
