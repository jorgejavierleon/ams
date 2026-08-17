<?php

namespace App\Http\Resources;

use App\Http\Controllers\My\WorkdayController;
use App\Models\MarkModification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One pending admin-requested correction for the Jornada tab's
 * pending-correction card (kolvi-mobile KMO-35): the proposed change against
 * the current mark, why it was requested, who opened it, and when the
 * employee's review window closes.
 *
 * Mirrors {@see WorkdayController::presentModification()},
 * the same data the web self-service list already shows, minus `notes` and
 * `date_label` (the card has no use for either) and plus `expires_at` — the
 * web page shows `created_ago` because it has no ongoing countdown to render;
 * the mobile card does, so it gets the raw naive deadline and formats its own
 * "Vence en 2 días" the way every other wire datetime in this API is read
 * verbatim rather than pre-formatted server-side.
 *
 * @mixin MarkModification
 */
class PendingMarkModificationResource extends JsonResource
{
    /**
     * @return array{id: int, workday_id: int, mark_type_label: string|null, original_time: string|null, proposed_time: string, reason: string|null, requested_by: string|null, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workday_id' => $this->workday_id,
            'mark_type_label' => $this->mark_type?->label(),
            'original_time' => $this->timeOf($this->original_date_time ?? $this->mark?->date_time),
            'proposed_time' => $this->timeOf($this->date_time),
            'reason' => $this->reason?->label(),
            'requested_by' => $this->createdBy?->name,
            'expires_at' => $this->reviewWindowStartedAt()
                ->addHours((int) config('ams.mark_modification_timeout_hours'))
                ->format('Y-m-d H:i:s'),
        ];
    }

    private function timeOf(mixed $dateTime): ?string
    {
        return $dateTime?->format('H:i');
    }
}
