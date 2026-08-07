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
     * @return array{mark_id: int, folio: string|null, hash: string, datetime: string, type: string, geo_status: string|null, employee_name: string|null, employee_rut: string|null, device_datetime: string|null, synced_at: string|null, captured_offline: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'mark_id' => $this->id,
            // The `N° comprobante` on the receipt (Art. 13), and the number an
            // employee quotes to HR — so the same one the emailed copy shows.
            'folio' => $this->folio,
            'hash' => $this->checksum,
            'datetime' => $this->date_time->format('Y-m-d H:i:s'),
            'type' => $this->type->value,
            // Null on a mark whose geofence was never evaluated — a web punch,
            // or one made before the endpoint evaluated any. The client reads
            // that as `unknown`, which is what it means.
            'geo_status' => $this->geo_status?->value,
            // From the snapshot on the mark, never from the live user: a
            // receipt reprinted years later must show who the employee was at
            // the punch, not who they are now.
            'employee_name' => $this->employee_name,
            // Undotted with its verifier digit, exactly as `users.rut` holds it.
            // There is one spelling of a Chilean RUT in the mobile client and it
            // is `formatRut`'s to choose, not the server's.
            'employee_rut' => $this->employee_rut,
            // Provenance, so a receipt opened after a sync can state its own
            // (Res. 38 Art. 10). The raw phone reading `datetime` was
            // adjudicated from — null on an online punch, which sends none.
            'device_datetime' => $this->device_datetime?->format('Y-m-d H:i:s'),
            // When the register received the punch. Equal to `datetime` online;
            // on a queued punch the gap between them is the queue's own age.
            'synced_at' => $this->synced_at?->format('Y-m-d H:i:s'),
            'captured_offline' => (bool) $this->captured_offline,
        ];
    }
}
