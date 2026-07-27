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
 * @mixin Mark
 */
class MarkResource extends JsonResource
{
    /**
     * @return array{mark_id: int, hash: string, datetime: string, type: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'mark_id' => $this->id,
            'hash' => $this->checksum,
            'datetime' => $this->date_time->toIso8601String(),
            'type' => $this->type->value,
        ];
    }
}
