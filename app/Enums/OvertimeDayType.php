<?php

namespace App\Enums;

use App\Models\Holiday;
use App\Services\Overtime\OvertimeExportDataset;
use App\Services\Reports\SundaysReportService;

/**
 * The nature of the calendar day an overtime hour was worked on (PRD §7.7):
 * an ordinary weekday, a Sunday, or a public holiday.
 *
 * Derived, never stored — {@see OvertimeExportDataset} resolves it from
 * {@see Holiday} and the same Sunday reasoning
 * {@see SundaysReportService} already uses for the DT
 * report, so there is exactly one definition of "Sunday or festivo" in the
 * codebase. This is the property KOL-12 needs to sort a payable hour into its
 * legal pay bucket (Código del Trabajo art. 32, 38).
 */
enum OvertimeDayType: string
{
    case Weekday = 'weekday';

    case Sunday = 'sunday';

    case Holiday = 'holiday';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.overtime.day_types.'.$this->value);
    }
}
