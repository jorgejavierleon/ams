<?php

namespace App\Http\Resources;

use App\Http\Controllers\My\DocumentController;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the employee's own documents for the mobile Documentos tab's list
 * (kolvi-mobile KMO-42). Mirrors what {@see DocumentController::index()}
 * already sends the web self-service list.
 *
 * `status`/`type`/`my_signature` are carried alongside the
 * `status_label`/`status_badge` pair KMO-42's own parser reads, so a future
 * reader ticket (KMO-43) has the same shape the web Inertia response already
 * gives without a breaking change to this list.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array{id: int, title: string, type: string|null, status: string, status_label: string, status_badge: string, published_at: string|null, my_signature: array{status: string, status_label: string, status_badge: string}|null, awaiting_me: bool}
     */
    public function toArray(Request $request): array
    {
        $mySignature = $this->signatures->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type?->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badge(),
            'published_at' => $this->published_at?->format('Y-m-d'),
            'my_signature' => $mySignature ? [
                'status' => $mySignature->status->value,
                'status_label' => $mySignature->status->label(),
                'status_badge' => $mySignature->status->badge(),
            ] : null,
            'awaiting_me' => $this->actionableSignatureFor($request->user()) !== null,
        ];
    }
}
