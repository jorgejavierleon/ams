<?php

namespace App\Enums;

use App\Services\Overtime\OvertimePayBucketClassifier;

/**
 * The legal pay bucket a payable overtime hour is routed into for a payroll
 * export (PRD-reports RF-1, KOL-12).
 *
 * Código del Trabajo art. 32 sets a single, uniform 50% recargo on every
 * overtime hour — there is no statutory second rate. The "HHEE 100%" column
 * clients and competing payroll tools ask for is not a distinct legal
 * percentage; it is how payroll systems route overtime worked on a Sunday or
 * public holiday to its own concept code, because that day also touches
 * art. 38's weekly-rest rules. So this enum buckets by *day type*, not by a
 * fictitious rate, and {@see OvertimePayBucketClassifier} names it
 * accordingly — an export template later maps each bucket to whatever haber
 * code the client has configured (Nubox or otherwise).
 */
enum OvertimePayBucket: string
{
    case OrdinaryDay = 'ordinary_day';

    case SundayOrHoliday = 'sunday_or_holiday';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.overtime.pay_buckets.'.$this->value);
    }
}
