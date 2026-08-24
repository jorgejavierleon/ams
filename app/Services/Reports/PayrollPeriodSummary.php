<?php

namespace App\Services\Reports;

use App\Models\Leave;
use App\Models\Workday;
use App\Services\Overtime\OvertimePayBucketBreakdown;
use App\Support\Duration;

/**
 * One employee's payroll figures for a period (quincena or mes), the
 * calculation core RF-1 and the Nubox export (RF-4) both read from
 * (PRD §2.3.3, KOL-13).
 *
 * Shaped after GeoVictoria's `Consolidated/Extended` endpoint as the PRD
 * recommends (worked/non-worked hours, overtime by pay bucket, lateness,
 * absence counts, days worked on a Sunday/holiday, and a paid/non-paid days
 * split by reason). The PRD's own field names are Spanish; renaming them to
 * that shape is deferred to whichever future ticket builds the API/export
 * layer, so this class stays plain English like the rest of the codebase.
 *
 * Nothing here is recomputed: every figure is read off {@see Workday},
 * {@see Leave} and the existing KOL-12/KOL-49 overtime services —
 * see {@see PayrollPeriodSummaryService} for where each one comes from.
 */
final readonly class PayrollPeriodSummary
{
    public function __construct(
        public int $userId,
        public Duration $workedHours,
        public Duration $nonWorkedHours,
        public Duration $totalLateness,
        public OvertimePayBucketBreakdown $overtime,
        public int $justifiedAbsenceDays,
        public int $unjustifiedAbsenceDays,
        public int $sundaysAndHolidaysWorked,
        public PaidDaysBreakdown $paidDays,
        public NonPaidDaysBreakdown $nonPaidDays,
    ) {}
}
