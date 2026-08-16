<?php

namespace App\Http\Resources;

use App\Models\Mark;
use App\Models\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One workday's detail for the mobile Jornada tab's day-detail screen
 * (kolvi-mobile KMO-34): the same minimal snake_case shape and leave-day
 * null-out convention as {@see WorkdayResource}, plus what the list resource
 * has no use for — the assigned shift's own scheduled window (§6: the
 * attendance strip's axis, not the mockup's fixed 08:00-18:00) and each
 * punch's own `mark_id`, so the app can retrieve that punch's comprobante
 * through the existing `GET /api/v1/marks/{mark}` rather than the day-detail
 * response trying to carry the whole receipt itself.
 *
 * `shift_start`/`shift_end` and each mark's `time` carry seconds
 * (`HH:mm:ss`) rather than `WorkdayResource`'s display-trimmed `HH:mm` —
 * kolvi-mobile's `@/api` reads a clock-time-of-day as a `NaiveTime`, which is
 * typed to that exact format, because the attendance strip does real minute
 * arithmetic on these values rather than only displaying them.
 *
 * @mixin Workday
 */
class WorkdayDetailResource extends JsonResource
{
    /**
     * @return array{date: string, status: string|null, status_label: string|null, status_badge: string|null, shift_start: string|null, shift_end: string|null, worked_time: string|null, extra_time: string|null, missing_time: string|null, leave_type_label: string|null, mark_in: array{time: string, mark_id: int}|null, mark_out: array{time: string, mark_id: int}|null}
     */
    public function toArray(Request $request): array
    {
        $onLeave = $this->leave_id !== null;

        return [
            'date' => $this->date->format('Y-m-d'),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_badge' => $this->status?->badge(),
            'shift_start' => $this->timeOfDay($this->shift_start_time),
            'shift_end' => $this->timeOfDay($this->shift_end_time),
            'worked_time' => $onLeave ? null : $this->trimSeconds($this->worked_time),
            'extra_time' => $onLeave ? null : $this->trimSeconds($this->extra_time),
            'missing_time' => $onLeave ? null : $this->trimSeconds($this->missing_time),
            'leave_type_label' => $onLeave ? $this->leave?->type->label() : null,
            'mark_in' => $this->presentMark($this->markIn),
            'mark_out' => $this->presentMark($this->markOut),
        ];
    }

    /**
     * @return array{time: string, mark_id: int}|null
     */
    private function presentMark(?Mark $mark): ?array
    {
        if ($mark === null) {
            return null;
        }

        return [
            'time' => $mark->date_time->format('H:i:s'),
            'mark_id' => $mark->id,
        ];
    }

    private function timeOfDay(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return Carbon::parse($time)->format('H:i:s');
    }

    private function trimSeconds(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return substr($time, 0, 5);
    }
}
