<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A technical incident: a window during which the electronic attendance system
 * was unavailable. Recorded per employer and surfaced read-only to Labor
 * Department (DT) inspectors as part of a Resolución 38 audit.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property Carbon $start_time
 * @property Carbon|null $end_time
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $duration
 */
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * A human-readable length of the outage, or null while it is still open
     * (no end time recorded yet).
     *
     * @return Attribute<string|null, never>
     */
    protected function duration(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->end_time
                ?->diffForHumans($this->start_time, CarbonInterface::DIFF_ABSOLUTE),
        );
    }
}
