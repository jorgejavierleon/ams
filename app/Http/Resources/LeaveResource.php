<?php

namespace App\Http\Resources;

use App\Http\Controllers\My\LeaveController;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the employee's own leave requests for the mobile Permisos tab's Mis
 * solicitudes screen (kolvi-mobile KMO-39). Mirrors what
 * {@see LeaveController::index()} already sends the
 * web self-service list, trimmed to what the mobile card shows.
 *
 * `approver_note` carries `rejection_reason` (design-decisions.md D-F5-d):
 * distinct from the requester's own `notes`, and null until an admin rejects
 * the request with one.
 *
 * @mixin Leave
 */
class LeaveResource extends JsonResource
{
    /**
     * @return array{id: int, type_label: string, status: string, status_label: string, status_badge: string, start_date: string, end_date: string, approver_note: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badge(),
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'approver_note' => $this->rejection_reason,
        ];
    }
}
