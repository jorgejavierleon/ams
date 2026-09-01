<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTablePerPage;
use App\Concerns\ResolvesTableSort;
use App\Models\User;
use App\Services\Reports\PayrollExportReadinessService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Auditable history of every payroll report export (RF-6, KOL-17): who
 * exported what, when, for which period, in which format, and — when the
 * selection had unresolved attendance data (KOL-14) — whether they confirmed
 * past the warning and what was unresolved.
 *
 * Extends the existing `spatie/laravel-activitylog` mechanism
 * {@see PayrollExportReadinessService::recordExport()}
 * already writes to, rather than a parallel audit table (AC #3): every export
 * call site already logs to the `payroll_export` log, tagged with the
 * `exported` event to keep this query separate from the `confirmed` entries.
 *
 * Visible to the tenant admin (gated by the same `View:PayrollReport`
 * permission as the reports themselves — RF-6 explicitly requires this be
 * reachable without superadmin access), and scoped to the current
 * organization via the `organization_id` every log entry already carries in
 * its properties (AC #5).
 */
class PayrollExportHistoryController extends Controller
{
    use ResolvesTablePerPage, ResolvesTableSort;

    /**
     * The report types a payroll export can be, in display order — mirrors
     * the `report_type` values {@see PayrollExportReadinessService::recordExport()}
     * is called with across every export call site.
     *
     * @var list<string>
     */
    public const REPORT_TYPES = ['payroll-summary', 'weekly-detail', 'period-movements', 'overtime-excess', 'employee-master'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'report_type' => ['nullable', 'string', 'in:'.implode(',', self::REPORT_TYPES)],
        ]);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $reportType = $filters['report_type'] ?? null;
        $perPage = $this->resolveTablePerPage($request);

        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['created_at'],
            'created_at',
            'desc',
        );

        $exports = Activity::query()
            ->with('causer:id,name,email')
            ->where('log_name', 'payroll_export')
            ->where('event', 'exported')
            ->where('properties->organization_id', CurrentOrganization::id())
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($reportType, fn ($query) => $query->where('properties->report_type', $reportType))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('payroll-reports/history', [
            'exports' => $exports->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'causer' => $activity->causer instanceof User
                    ? ['name' => $activity->causer->name, 'email' => $activity->causer->email]
                    : null,
                'report_type' => $activity->properties['report_type'] ?? null,
                'period_start' => $this->formatDate($activity->properties['period_start'] ?? null),
                'period_end' => $this->formatDate($activity->properties['period_end'] ?? null),
                'format' => $activity->properties['format'] ?? null,
                'employee_count' => count($activity->properties['employee_ids'] ?? []),
                'warned' => (bool) ($activity->properties['warned'] ?? false),
                'confirmed' => (bool) ($activity->properties['confirmed'] ?? false),
                'finding_types' => $activity->properties['finding_types'] ?? [],
                'filters' => $activity->properties['filters'] ?? [],
                'created_at' => $activity->created_at?->format('Y-m-d H:i:s') ?? '',
            ]),
            'reportTypes' => self::REPORT_TYPES,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'report_type' => $reportType,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    private function formatDate(?string $date): ?string
    {
        return $date === null ? null : Carbon::parse($date)->format('d-m-Y');
    }
}
