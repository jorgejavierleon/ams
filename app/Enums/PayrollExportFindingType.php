<?php

namespace App\Enums;

use App\Services\Reports\PayrollExportReadinessService;

/**
 * The kind of unresolved attendance data {@see PayrollExportReadinessService}
 * can find for a period/employee selection (KOL-14, PRD RF-2).
 */
enum PayrollExportFindingType: string
{
    case PendingMarkModification = 'pending_mark_modification';
    case IrregularWorkday = 'irregular_workday';
    case IncompleteWorkday = 'incomplete_workday';
    case OpenIncident = 'open_incident';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.payroll_export.finding_types.'.$this->value);
    }

    /**
     * Whether a finding of this type must be explicitly confirmed before an
     * export can proceed. An open technical incident is surfaced for context
     * only — it is not something RRHH can resolve from this screen, so it
     * never blocks on its own (KOL-14 AC #2).
     */
    public function blocking(): bool
    {
        return $this !== self::OpenIncident;
    }
}
