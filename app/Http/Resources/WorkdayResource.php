<?php

namespace App\Http\Resources;

use App\Http\Controllers\My\WorkdayController;
use App\Models\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the Jornada tab's Historial screen (kolvi-mobile KMO-33): a
 * computed day of attendance, mirroring what {@see WorkdayController::index}
 * already sends the web self-service list, minus the pending-modification
 * count that screen shows separately.
 *
 * A day covered by an approved leave carries `leave_type_label` in place of
 * the worked/extra/missing figures — the leave justifies the whole day, so
 * the figures would read as zero rather than as absent, which is not the same
 * claim.
 *
 * @mixin Workday
 */
class WorkdayResource extends JsonResource
{
    /**
     * @return array{date: string, date_label: string, weekday: string, status: string|null, status_label: string|null, status_badge: string|null, worked_time: string|null, extra_time: string|null, missing_time: string|null, leave_type_label: string|null}
     */
    public function toArray(Request $request): array
    {
        $onLeave = $this->leave_id !== null;

        return [
            'date' => $this->date->format('Y-m-d'),
            'date_label' => $this->date->isoFormat('ddd D [de] MMM'),
            'weekday' => $this->date->isoFormat('dddd'),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_badge' => $this->status?->badge(),
            'worked_time' => $onLeave ? null : $this->trimSeconds($this->worked_time),
            'extra_time' => $onLeave ? null : $this->trimSeconds($this->extra_time),
            'missing_time' => $onLeave ? null : $this->trimSeconds($this->missing_time),
            'leave_type_label' => $onLeave ? $this->leave?->type->label() : null,
        ];
    }

    private function trimSeconds(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return substr($time, 0, 5);
    }
}
