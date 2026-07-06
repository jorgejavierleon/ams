<?php

namespace App\Models\Concerns;

use App\Support\Rut;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Stores the model's `rut` attribute in a canonical `body-dv` form and exposes
 * a `formatted_rut` accessor for display (`12.345.678-5`).
 */
trait FormatedRut
{
    /**
     * @return Attribute<string|null, string|null>
     */
    protected function rut(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Rut::normalize($value),
        );
    }

    public function getFormattedRutAttribute(): ?string
    {
        return $this->rut === null ? null : Rut::format($this->rut);
    }
}
