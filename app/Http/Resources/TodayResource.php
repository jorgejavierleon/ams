<?php

namespace App\Http\Resources;

use App\Support\TodaySummary;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The mobile home screen in one payload: today's date, the shift scheduled for
 * it, where the employee is in their day and the week so far.
 *
 * Every date and time here is a **naive wall-clock string** — `Y-m-d` and
 * `H:i:s`, no offset. Deliberately not `toIso8601String()`, which is what
 * {@see MarkResource} emits: a shift window stamped with an offset is re-read in
 * the device's timezone, and a window silently moved an hour is a different
 * legal fact under Resolución 38 Art. 8 with nothing on screen to say it moved.
 * The app rejects an offset outright rather than render one.
 *
 * @mixin TodaySummary
 */
class TodayResource extends JsonResource
{
    /**
     * @return array{
     *     date: string,
     *     shift: array{premise: string|null, start_time: string|null, end_time: string|null, lunch_start_time: string|null, lunch_end_time: string|null}|null,
     *     punch: array{state: string}|null,
     *     week: array{worked_hours: float, contracted_hours: float},
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'shift' => $this->shift(),
            // Null — rather than a fabricated `before` — for an employee who
            // does not punch at all. The app hides the punch surface; a state
            // would put "aún no marcas entrada" in front of someone who never
            // will.
            'punch' => $this->punchState === null
                ? null
                : ['state' => $this->punchState->value],
            'week' => [
                'worked_hours' => $this->workedHours,
                'contracted_hours' => $this->contractedHours,
            ],
        ];
    }

    /**
     * Today's scheduled window, or null when nothing is scheduled — a free day,
     * or an employee between assignments. The app has its own empty state for
     * that and does not need to be told which of the two it was.
     *
     * @return array{premise: string|null, start_time: string|null, end_time: string|null, lunch_start_time: string|null, lunch_end_time: string|null}|null
     */
    private function shift(): ?array
    {
        $shiftDay = $this->shiftDay;

        if ($shiftDay === null) {
            return null;
        }

        // The colación window travels both ends or neither: the app draws no
        // row for half a window, because `13:00 – ` on a card reads as a
        // rendering bug rather than as missing data.
        $hasLunch = $shiftDay->lunch_start_time !== null && $shiftDay->lunch_end_time !== null;

        return [
            'premise' => $this->premiseLabel,
            'start_time' => $this->wallClock($shiftDay->start_time),
            'end_time' => $this->wallClock($shiftDay->end_time),
            'lunch_start_time' => $hasLunch ? $this->wallClock($shiftDay->lunch_start_time) : null,
            'lunch_end_time' => $hasLunch ? $this->wallClock($shiftDay->lunch_end_time) : null,
        ];
    }

    /**
     * A scheduled time as the wall-clock reading it is, seconds included.
     */
    private function wallClock(?CarbonInterface $time): ?string
    {
        return $time?->format('H:i:s');
    }
}
