<?php

namespace App\Support;

use App\Enums\PunchState;
use App\Http\Controllers\Api\UpcomingShiftsController;
use App\Http\Resources\UpcomingShiftsResource;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The Jornada tab's Próximos screen in one payload: today's shift and where
 * the employee is in it, then the schedule for the days after it.
 *
 * Assembled by {@see UpcomingShiftsController},
 * shaped for the wire by {@see UpcomingShiftsResource}.
 */
class UpcomingShiftsSummary
{
    /**
     * @param  CarbonInterface  $date  The employee's own wall-clock day, not the server's — same reading TodaySummary's own $date takes. Present unconditionally: `today` below answers "what is scheduled", not "what date is it", and the mobile client needs the latter even on a free day to know which upcoming row is literally tomorrow.
     * @param  ScheduledDay|null  $today  Null when nothing is scheduled today — a free day, or no active assignment.
     * @param  PunchState|null  $punchState  Null for an employee who does not hold ClockOwn:Mark, same gating TodayController applies — absent from the wire, never a fabricated state.
     * @param  Collection<int, ScheduledDay>  $days  The schedule for the requested horizon, starting the day after today.
     */
    public function __construct(
        public readonly CarbonInterface $date,
        public readonly ?ScheduledDay $today,
        public readonly ?PunchState $punchState,
        public readonly Collection $days,
    ) {}
}
