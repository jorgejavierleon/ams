<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Services\Documents\DocumentVariableResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One document's full detail for the mobile Documentos tab's reader
 * (kolvi-mobile KMO-43). `body` is the already-resolved
 * {@see DocumentVariableResolver} output the controller hands in — never the
 * raw `{{variable}}` template.
 *
 * @mixin Document
 */
class DocumentDetailResource extends JsonResource
{
    public function __construct(Document $resource, private readonly string $body)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: int, title: string, status_label: string, status_badge: string, body: string, published_at: string|null, awaiting_me: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badge(),
            'body' => $this->body,
            'published_at' => $this->published_at?->format('Y-m-d'),
            'awaiting_me' => $this->actionableSignatureFor($request->user()) !== null,
        ];
    }
}
