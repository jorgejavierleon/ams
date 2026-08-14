<?php

namespace App\Support;

use App\Http\Resources\UpcomingShiftsResource;
use App\Services\ShiftScheduleResolver;
use Carbon\CarbonInterface;

/**
 * One calendar date on the Jornada tab's Próximos screen: the shift scheduled
 * for it, or why there isn't one to show as an ordinary shift.
 *
 * `leaveTypeLabel` and `holidayName` are mutually exclusive with the time
 * fields — a date carrying either has `startTime`/`endTime`/the lunch pair all
 * null, because the schedule that would have applied is beside the point once
 * an approved leave or a holiday the shift does not work has taken the date.
 *
 * Assembled by {@see ShiftScheduleResolver}, shaped for the wire
 * by {@see UpcomingShiftsResource}.
 */
class ScheduledDay
{
    public function __construct(
        public readonly CarbonInterface $date,
        public readonly ?string $premise,
        public readonly ?CarbonInterface $startTime,
        public readonly ?CarbonInterface $endTime,
        public readonly ?CarbonInterface $lunchStartTime,
        public readonly ?CarbonInterface $lunchEndTime,
        public readonly ?string $leaveTypeLabel,
        public readonly ?string $holidayName,
    ) {}
}
