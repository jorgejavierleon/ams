<?php

namespace App\Models;

use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\MarkModificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A request to modify (or add) an attendance mark on a workday. Pending
 * modifications flag the workday for HR review; the full create/approve
 * workflow lands with the workday modify action.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $workday_id
 * @property int|null $mark_id
 * @property int $user_id
 * @property int|null $created_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property MarkModificationReason|null $reason
 * @property MarkModificationStatus|null $status
 * @property Carbon $date_time
 * @property MarkType|null $mark_type
 * @property string|null $notes
 * @property string $ulid
 */
class MarkModification extends Model
{
    /** @use HasFactory<MarkModificationFactory> */
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MarkModificationStatus::class,
            'reason' => MarkModificationReason::class,
            'mark_type' => MarkType::class,
            'date_time' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Generate the public ULID on creation without pulling in the full
     * {@see HasUlids} key-strategy override (the primary key stays an integer).
     */
    protected static function booted(): void
    {
        static::creating(function (MarkModification $modification): void {
            if ($modification->ulid === null) {
                $modification->ulid = strtolower((string) Str::ulid());
            }
        });
    }

    /**
     * @return BelongsTo<Workday, $this>
     */
    public function workday(): BelongsTo
    {
        return $this->belongsTo(Workday::class);
    }

    /**
     * @return BelongsTo<Mark, $this>
     */
    public function mark(): BelongsTo
    {
        return $this->belongsTo(Mark::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
