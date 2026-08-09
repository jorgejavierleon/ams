<?php

namespace App\Enums;

use App\Models\OvertimeAuthorization;
use App\Services\WorkdayCalculator;

/**
 * Why a day's overtime figures are not trustworthy enough to pay from (PRD
 * §7.4). An anomaly is not a legal-cap breach — that is KOL-41 — it means the
 * underlying data itself cannot be trusted yet, and it blocks the day from
 * reaching {@see OvertimeAuthorization} approval until a human has looked at
 * it. Never blocking at the point of entry (Resolución 38 art. 45.2): a mark
 * or a shift always saves, whatever this enum would say about the day it
 * belongs to.
 *
 * The first two reuse {@see WorkdayStatus} rather than re-deriving the same
 * condition a second way — {@see WorkdayCalculator} sets them straight off the
 * status it already computed for the day.
 */
enum AnomalyFlagReason: string
{
    /** WorkdayStatus::Irregular: marks exist but there is no assigned shift to measure them against. */
    case NoAssignedShift = 'no_assigned_shift';

    /** WorkdayStatus::Incomplete: only one of the day's two marks exists. */
    case IncompleteMarks = 'incomplete_marks';

    /** The contract is not active (before its start or after its end) on the day's own date. */
    case ContractNotActive = 'contract_not_active';

    /** A mark on the day fell outside the premise's geofence. */
    case OutsideGeofence = 'outside_geofence';

    /** The week's total calculated overtime exceeds the tenant's configured threshold. */
    case PeriodVolumeExceeded = 'period_volume_exceeded';

    /**
     * Human-readable, translated explanation a supervisor can act on.
     */
    public function label(): string
    {
        return __('ui.overtime.anomaly_reasons.'.$this->value);
    }
}
