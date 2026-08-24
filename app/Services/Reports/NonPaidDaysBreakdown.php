<?php

namespace App\Services\Reports;

use App\Enums\LeaveType;

/**
 * One employee's unpaid days for a payroll period (PRD §2.3.3, KOL-13),
 * mirroring GeoVictoria's `Consolidated/Extended.NonPaidDays`.
 *
 * `medicalLeaveDays` is unpaid by the employer specifically (it is
 * subsidised through COMPIN/Isapre instead), which is exactly why
 * GeoVictoria files it under `NonPaidDays` rather than alongside vacation.
 * `unpaidLeaveDays` also absorbs {@see LeaveType::Other}, a catch-all type
 * the Código del Trabajo gives no paid-time-off guarantee of its own.
 */
final readonly class NonPaidDaysBreakdown
{
    public function __construct(
        public int $unjustifiedAbsenceDays,
        public int $medicalLeaveDays,
        public int $unpaidLeaveDays,
    ) {}
}
