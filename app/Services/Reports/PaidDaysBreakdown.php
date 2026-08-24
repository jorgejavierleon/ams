<?php

namespace App\Services\Reports;

/**
 * One employee's paid days for a payroll period (PRD §2.3.3, KOL-13),
 * mirroring GeoVictoria's `Consolidated/Extended.PaidDays`: days actually
 * worked, plus days off the employer still owes pay for.
 *
 * `workedDays` counts a day the moment {@see AttendanceReportService} marks
 * it attended, regardless of what shift or leave also touches it — an
 * employee who worked is never counted as being on leave instead.
 */
final readonly class PaidDaysBreakdown
{
    public function __construct(
        public int $workedDays,
        public int $vacationDays,
        public int $paidLeaveDays,
    ) {}
}
