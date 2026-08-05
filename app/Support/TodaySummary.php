<?php

namespace App\Support;

use App\Enums\PunchState;
use App\Http\Controllers\Api\TodayController;
use App\Http\Resources\TodayResource;
use App\Models\ShiftDay;
use Carbon\CarbonInterface;

/**
 * Everything the employee mobile app's home screen draws, gathered in one
 * place: the day it is for the employee, the shift scheduled for it, where they
 * are in it, and the week so far against what they are contracted to work.
 *
 * Assembled by {@see TodayController} and shaped for the wire by
 * {@see TodayResource}.
 */
class TodaySummary
{
    /**
     * @param  CarbonInterface  $date  The employee's own wall-clock day, not the server's.
     * @param  ShiftDay|null  $shiftDay  The day scheduled for them, or null when the day is free or they hold no assignment.
     * @param  string|null  $premiseLabel  Where the shift is worked, for the shift card.
     * @param  PunchState|null  $punchState  Null for an employee who does not punch at all.
     * @param  float  $workedHours  Worked time from Monday to today.
     * @param  float  $contractedHours  The shift's contracted weekly total.
     */
    public function __construct(
        public readonly CarbonInterface $date,
        public readonly ?ShiftDay $shiftDay,
        public readonly ?string $premiseLabel,
        public readonly ?PunchState $punchState,
        public readonly float $workedHours,
        public readonly float $contractedHours,
    ) {}
}
