<?php

namespace App\Http\Resources;

use App\Models\Mark;
use App\Observers\MarkObserver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The mobile API representation of an attendance mark: the identity and
 * integrity data a device needs to confirm a punch was recorded, mirroring the
 * legal snapshot stamped by {@see MarkObserver}.
 *
 * `datetime` is a **naive Santiago wall-clock string**, like every datetime this
 * API puts on the wire (see {@see TodayResource}). Deliberately not
 * `toIso8601String()`: an offset is re-read in whatever timezone the device
 * believes it is in, and a legally-binding punch that silently moves by an hour
 * twice a year is the one thing an attendance receipt may not do.
 *
 * @mixin Mark
 */
class MarkResource extends JsonResource
{
    /**
     * @return array{mark_id: int, hash: string, datetime: string, type: string, geo_status: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'mark_id' => $this->id,
            'hash' => $this->checksum,
            'datetime' => $this->date_time->format('Y-m-d H:i:s'),
            'type' => $this->type->value,
            // Null on a mark whose geofence was never evaluated — a web punch,
            // or one made before the endpoint evaluated any. The client reads
            // that as `unknown`, which is what it means.
            'geo_status' => $this->geo_status?->value,
        ];
    }
}
