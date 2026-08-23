<?php

namespace App\Services\Overtime;

use App\Support\Duration;

/**
 * One employee's overtime for a period, sorted into every disposition it can
 * legally have (KOL-12). Four buckets, not two, because a day's calculated
 * overtime always lands in exactly one of them — this is what makes
 * {@see OvertimePayBucketClassifier::forPeriod()}'s totals reconcile against
 * the sum of `calculated_overtime` with nothing lost or double-counted:
 *
 * - {@see self::$ordinaryDayHours} / {@see self::$sundayOrHolidayHours}:
 *   payable now, money-owed, sourced from {@see OvertimeExportDataset} —
 *   structurally approved-only, so these can never contain a pending or
 *   revoked hour.
 * - {@see self::$compensatedInRestDaysHours}: authorised and legitimate, but
 *   not money — the employee is being paid in time off (KOL-47) and the
 *   balance has not yet lapsed unconsumed, so it owes nothing to payroll
 *   this period.
 * - {@see self::$unauthorizedHours}: worked but never decided on, or decided
 *   against — reported for visibility (KOL-46), never payable.
 */
final readonly class OvertimePayBucketBreakdown
{
    public function __construct(
        public int $userId,
        public Duration $ordinaryDayHours,
        public Duration $sundayOrHolidayHours,
        public Duration $compensatedInRestDaysHours,
        public Duration $unauthorizedHours,
    ) {}
}
