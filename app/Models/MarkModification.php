<?php

namespace App\Models;

use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
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
 * @property Carbon|null $notified_at
 * @property MarkModificationReason|null $reason
 * @property MarkModificationStatus|null $status
 * @property Carbon $date_time
 * @property Carbon|null $original_date_time
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
            'original_date_time' => 'datetime',
            'reviewed_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Generate the public ULID on creation without pulling in the full
     * {@see HasUlids} key-strategy override (the primary key stays an integer).
     */
    protected static function booted(): void
    {
        static::creating(function (MarkModification $modification): void {
            if ($modification->getAttribute('ulid') === null) {
                $modification->ulid = strtolower((string) Str::ulid());
            }
        });
    }

    /**
     * Whether the modification is still awaiting the employee's review.
     */
    public function isPending(): bool
    {
        return $this->status === MarkModificationStatus::Pending;
    }

    /**
     * When the employee's opposition window starts counting: the moment the
     * notification was sent, falling back to creation time for rows created
     * before send-time was tracked (Resolución 38, art. 40 c).
     */
    public function reviewWindowStartedAt(): CarbonInterface
    {
        return $this->notified_at ?? $this->created_at;
    }

    /**
     * Whether a still-pending modification has outlived its opposition window.
     * Past this point the employee can no longer oppose from the public page and
     * the change consolidates automatically (art. 40 d), handled by the
     * scheduled `mark-modifications:approve-overdue` command. The window length
     * is configured by `ams.mark_modification_timeout_hours`.
     */
    public function isExpired(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $timeoutHours = (int) config('ams.mark_modification_timeout_hours');

        return $this->reviewWindowStartedAt()->addHours($timeoutHours)->isPast();
    }

    /**
     * Whether the employee may still act on the modification (pending and within
     * the review window).
     */
    public function isActionable(): bool
    {
        return $this->isPending() && ! $this->isExpired();
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
