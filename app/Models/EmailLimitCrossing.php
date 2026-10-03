<?php

namespace App\Models;

use Database\Factories\EmailLimitCrossingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per organization per calendar month per threshold crossed
 * (KOL-137.3). A row's existence is both the platform-admin-visible alert
 * and the dedup guard: {@see Organization::recordEmailLimitCrossing()}
 * writes it with firstOrCreate() so a threshold fires at most once per
 * organization/month regardless of how many sends hit it.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $type
 * @property Carbon $month
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable(['organization_id', 'type', 'month'])]
class EmailLimitCrossing extends Model
{
    /** @use HasFactory<EmailLimitCrossingFactory> */
    use HasFactory;

    public const TYPE_SOFT = 'soft';

    public const TYPE_HARD = 'hard';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
