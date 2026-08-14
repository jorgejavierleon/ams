<?php

namespace App\Http\Resources;

use App\Support\ScheduledDay;
use App\Support\UpcomingShiftsSummary;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Jornada tab's Próximos screen in one payload.
 *
 * Every date and time is a **naive wall-clock string** — `Y-m-d` and `H:i:s` —
 * for the same reason {@see TodayResource} is: a shift window re-read in the
 * device's timezone is a different legal fact under Resolución 38 Art. 8 with
 * nothing on screen to say it moved.
 *
 * @mixin UpcomingShiftsSummary
 */
class UpcomingShiftsResource extends JsonResource
{
    /**
     * @return array{
     *     date: string,
     *     today: array{date: string, premise: string|null, start_time: string|null, end_time: string|null, lunch_start_time: string|null, lunch_end_time: string|null, leave_type_label: string|null, holiday_name: string|null, punch_state?: string}|null,
     *     days: array<int, array{date: string, premise: string|null, start_time: string|null, end_time: string|null, lunch_start_time: string|null, lunch_end_time: string|null, leave_type_label: string|null, holiday_name: string|null}>,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            // The employee's own day, present even when `today` below is null —
            // a free day still has a date, and the client needs it to know
            // which upcoming row is literally tomorrow.
            'date' => $this->date->format('Y-m-d'),
            'today' => $this->today === null ? null : [
                ...$this->day($this->today),
                // Omitted rather than sent null when the employee holds no
                // ClockOwn:Mark — the app's own reading of an absent punch
                // surface, not a state to report for one that does not exist.
                ...($this->punchState === null ? [] : ['punch_state' => $this->punchState->value]),
            ],
            'days' => $this->days->map(fn (ScheduledDay $day): array => $this->day($day))->values()->all(),
        ];
    }

    /**
     * @return array{date: string, premise: string|null, start_time: string|null, end_time: string|null, lunch_start_time: string|null, lunch_end_time: string|null, leave_type_label: string|null, holiday_name: string|null}
     */
    private function day(ScheduledDay $day): array
    {
        return [
            'date' => $day->date->format('Y-m-d'),
            'premise' => $day->premise,
            'start_time' => $this->wallClock($day->startTime),
            'end_time' => $this->wallClock($day->endTime),
            'lunch_start_time' => $this->wallClock($day->lunchStartTime),
            'lunch_end_time' => $this->wallClock($day->lunchEndTime),
            'leave_type_label' => $day->leaveTypeLabel,
            'holiday_name' => $day->holidayName,
        ];
    }

    private function wallClock(?CarbonInterface $time): ?string
    {
        return $time?->format('H:i:s');
    }
}
