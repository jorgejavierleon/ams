<?php

namespace App\Services\Overtime;

/**
 * The excess policy in force for one calculated day: today only the question of
 * whether an early arrival contributes to the calculated overtime (PRD §7.2).
 *
 * A value object rather than a raw boolean so a later per-shift or per-day rule
 * adds a field here and changes {@see OvertimeExcessPolicyResolver}, without
 * touching the arithmetic in {@see ShiftExcess}.
 */
final readonly class OvertimeExcessPolicy
{
    public function __construct(public bool $countsPreShiftExcess) {}

    /**
     * The conservative policy: only work after the shift ends counts. What a
     * brand-new organization gets, and what applies when no organization can be
     * resolved for the day at all.
     */
    public static function postShiftOnly(): self
    {
        return new self(countsPreShiftExcess: false);
    }
}
