<?php

namespace App\Models;

use App\Enums\OvertimeRequestStatus;
use App\Exceptions\OvertimeRequestDecisionRefused;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\OvertimeRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee's ask for overtime hours ahead of working them (PRD §7.1, Mode
 * A, KOL-45). Deliberately not the same row as {@see OvertimeAuthorization} —
 * that record snapshots an already-computed {@see Workday}, which a request
 * made ahead of time has none of yet.
 *
 * Approving a request is a green light, not a payable hour: whatever the
 * employee actually works still goes through the calculation engine and,
 * once computed, a human decision on the authorisation record. A rejected or
 * unanswered request therefore never produces a payable hour on its own —
 * there is no write path from this model to one.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property Carbon $date
 * @property string $requested_hours
 * @property string|null $reason
 * @property OvertimeRequestStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $decision_reason
 */
class OvertimeRequest extends Model
{
    /** @use HasFactory<OvertimeRequestFactory> */
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OvertimeRequestStatus::class,
            'date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OvertimeRequest $request): void {
            $status = $request->getAttribute('status');
            $status = $status instanceof OvertimeRequestStatus
                ? $status
                : OvertimeRequestStatus::from((string) $status);

            if ($status !== OvertimeRequestStatus::Pending
                && ($request->reviewed_by === null || $request->reviewed_at === null)) {
                throw OvertimeRequestDecisionRefused::withoutAReviewer();
            }

            if ($status === OvertimeRequestStatus::Rejected && trim((string) $request->decision_reason) === '') {
                throw OvertimeRequestDecisionRefused::withoutAReason();
            }
        });
    }

    /**
     * Approve the request — the employee's green light to work the hours.
     * Never itself creates a payable hour; that still takes the authorisation
     * record once the day is actually worked and calculated.
     *
     * @param  User  $reviewer  The person deciding. Required — there is no other signature.
     */
    public function approve(User $reviewer, ?string $reason = null): self
    {
        $this->assertSameOrganizationAs($reviewer);

        $this->forceFill([
            'status' => OvertimeRequestStatus::Approved,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'decision_reason' => $reason,
        ])->save();

        return $this;
    }

    /**
     * Reject the request. Does not stop the employee from working the day —
     * it only means the hours, if worked, arrive at the authorisation queue
     * without a prior request behind them.
     *
     * @param  User  $reviewer  The person deciding. Required — there is no other signature.
     * @param  string  $reason  Why. A rejection without one is unanswerable to the employee.
     */
    public function reject(User $reviewer, string $reason): self
    {
        $this->assertSameOrganizationAs($reviewer);

        $this->forceFill([
            'status' => OvertimeRequestStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'decision_reason' => $reason,
        ])->save();

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === OvertimeRequestStatus::Pending;
    }

    /**
     * @param  Builder<OvertimeRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', OvertimeRequestStatus::Pending);
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
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * A reviewer from another employer is refused before the write, not after
     * the read.
     */
    private function assertSameOrganizationAs(User $reviewer): void
    {
        if ($this->organization_id !== $reviewer->organization_id) {
            throw OvertimeRequestDecisionRefused::byAnotherTenant();
        }
    }
}
