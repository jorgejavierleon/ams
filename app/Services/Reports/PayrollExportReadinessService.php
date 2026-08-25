<?php

namespace App\Services\Reports;

use App\Enums\PayrollExportFindingType;
use App\Enums\WorkdayStatus;
use App\Models\Incident;
use App\Models\MarkModification;
use App\Models\User;
use App\Models\Workday;
use App\Support\CurrentOrganization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Checks whether an employee/period selection is safe to export to payroll
 * (KOL-14, PRD RF-2): irregular and incomplete workdays, mark modifications
 * still pending approval, and open technical incidents overlapping the
 * period. Talana blocks the traspaso outright on an invalid mark; this
 * softer version lists what is wrong and lets the user proceed after an
 * explicit confirmation — a client closing payroll on a deadline must still
 * be able to export while knowing exactly what is unresolved.
 *
 * Runs on the same employee/period selection the export itself uses, next to
 * {@see PayrollPeriodSummaryService} rather than inside any one report, so
 * every future export screen checks the same thing the same way.
 *
 * **Organization scoping.** Mirrors {@see PayrollPeriodSummaryService}: an id
 * passed in explicitly is first intersected against the current
 * organization's own employees, before any other query runs.
 */
class PayrollExportReadinessService
{
    /**
     * @param  list<int>  $userIds
     */
    public function check(Carbon $start, Carbon $end, array $userIds): PayrollExportReadiness
    {
        if ($userIds === []) {
            return new PayrollExportReadiness(collect());
        }

        $scopedUserIds = array_values(array_map(
            intval(...),
            User::query()
                ->where('organization_id', CurrentOrganization::id())
                ->whereIn('id', $userIds)
                ->pluck('id')
                ->all(),
        ));

        if ($scopedUserIds === []) {
            return new PayrollExportReadiness(collect());
        }

        $findings = $this->workdayFindings($start, $end, $scopedUserIds)
            ->merge($this->openIncidentFindings($end));

        return new PayrollExportReadiness($findings);
    }

    /**
     * Record that the user was shown these findings and chose to export
     * anyway — the audit trail AC #6 requires, so it is later provable that
     * they were warned. Logged through the activity-log package already
     * installed for exactly this (composer.json), not a parallel mechanism.
     */
    public function recordConfirmation(User $confirmedBy, Carbon $start, Carbon $end, PayrollExportReadiness $readiness): void
    {
        activity('payroll_export')
            ->causedBy($confirmedBy)
            ->withProperties([
                'organization_id' => CurrentOrganization::id(),
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'employee_ids' => $readiness->findings->pluck('userId')->filter()->unique()->values()->all(),
                'finding_types' => $readiness->findings
                    ->map(fn (PayrollExportFinding $finding): string => $finding->type->value)
                    ->unique()
                    ->values()
                    ->all(),
                'finding_count' => $readiness->findings->count(),
            ])
            ->log('Confirmed payroll export despite unresolved attendance data');
    }

    /**
     * One bulk pass over the period's workdays for the selected employees,
     * carrying each one's pending mark modifications along via the existing
     * {@see Workday::pendingMarkModifications()} relation — a query, not new
     * plumbing — so this stays two queries regardless of how many employees
     * or days are involved (AC #7).
     *
     * @param  list<int>  $userIds
     * @return Collection<int, PayrollExportFinding>
     */
    private function workdayFindings(Carbon $start, Carbon $end, array $userIds): Collection
    {
        $problemStatuses = [WorkdayStatus::Irregular, WorkdayStatus::Incomplete];

        $workdays = Workday::query()
            ->whereIn('user_id', $userIds)
            ->betweenDates($start, $end)
            ->where(function ($query) use ($problemStatuses): void {
                $query->whereIn('status', $problemStatuses)
                    ->orWhereHas('pendingMarkModifications');
            })
            ->with('pendingMarkModifications:id,workday_id,user_id')
            ->get(['id', 'user_id', 'date', 'status']);

        return $workdays->flatMap(function (Workday $workday) use ($problemStatuses): array {
            $findings = [];

            if (in_array($workday->status, $problemStatuses, true)) {
                $findings[] = new PayrollExportFinding(
                    type: $workday->status === WorkdayStatus::Irregular
                        ? PayrollExportFindingType::IrregularWorkday
                        : PayrollExportFindingType::IncompleteWorkday,
                    userId: $workday->user_id,
                    date: $workday->date,
                    reason: __('ui.payroll_export.findings.'.$workday->status->value),
                    resolutionUrl: route('workdays.show', $workday),
                );
            }

            foreach ($workday->pendingMarkModifications as $modification) {
                /** @var MarkModification $modification */
                $findings[] = new PayrollExportFinding(
                    type: PayrollExportFindingType::PendingMarkModification,
                    userId: $modification->user_id,
                    date: $workday->date,
                    reason: __('ui.payroll_export.findings.pending_mark_modification'),
                    resolutionUrl: route('workdays.show', $workday),
                );
            }

            return $findings;
        });
    }

    /**
     * Open technical incidents (no `end_time`) that started on or before the
     * period ends — attendance may be missing for reasons outside the
     * employee's control, surfaced as context rather than tied to one row
     * (AC #2).
     *
     * @return Collection<int, PayrollExportFinding>
     */
    private function openIncidentFindings(Carbon $end): Collection
    {
        return Incident::query()
            ->whereNull('end_time')
            ->where('start_time', '<=', $end)
            ->get(['id', 'start_time', 'description'])
            ->map(fn (Incident $incident): PayrollExportFinding => new PayrollExportFinding(
                type: PayrollExportFindingType::OpenIncident,
                userId: null,
                date: null,
                reason: __('ui.payroll_export.findings.open_incident', [
                    'date' => $incident->start_time->format('d-m-Y H:i'),
                    'description' => $incident->description,
                ]),
                resolutionUrl: null,
            ));
    }
}
