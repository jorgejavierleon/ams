<?php

namespace App\Models;

use App\Enums\OvertimeAuthorizationStatus;
use App\Exceptions\OvertimeDecisionRefused;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Duration;
use Carbon\CarbonInterface;
use Database\Factories\OvertimeAuthorizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One employee's overtime for one day, and the human decision on it — the
 * record of PRD §8 and the only place a payable hour can be born.
 *
 * The calculation engine cannot write here (KOL-39) and the export can read
 * nothing else (KOL-49), so the two ends of the chain meet on this row. What a
 * day's marks imply is on {@see Workday}; what the employer owes is here.
 *
 * **Three figures, kept apart.** `calculated_hours` (OHC) is the engine's, taken
 * as a snapshot when the record opens. `requested_hours` (OHR) is the employee's
 * under Mode A. `authorized_hours` (OHA) is what the approver granted.
 * `final_hours` is what payroll gets. Keeping all four makes the last one
 * explainable: an accountant asking why two hours and not three reads the answer
 * off the row instead of re-deriving a month of attendance.
 *
 * **Nothing here ages into being payable.** {@see OvertimeAuthorizationStatus}
 * has no lapsed case, and {@see self::booted()} refuses to persist a decided row
 * without the person who decided it, so a record nobody acts on stays pending
 * for as long as that lasts and is simply never exported (PRD §7.5).
 *
 * Hours worked beyond what was authorised are not dropped and not paid: they
 * stay readable as {@see self::unauthorizedOvertime()}, which is what KOL-13 and
 * KOL-24 report on.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $workday_id
 * @property int $user_id
 * @property Carbon $date
 * @property string|null $calculated_hours
 * @property string|null $requested_hours
 * @property string|null $authorized_hours
 * @property string|null $final_hours
 * @property OvertimeAuthorizationStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $reason
 * @property int|null $overtime_pact_id
 */
class OvertimeAuthorization extends Model
{
    /** @use HasFactory<OvertimeAuthorizationFactory> */
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OvertimeAuthorizationStatus::class,
            'date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The guarantees of PRD §7.5 and §7.4, enforced where every write has to
     * pass rather than at the call sites that remember to ask.
     */
    protected static function booted(): void
    {
        static::saving(function (OvertimeAuthorization $authorization): void {
            $status = $authorization->getAttribute('status');
            $status = $status instanceof OvertimeAuthorizationStatus
                ? $status
                : OvertimeAuthorizationStatus::from((string) $status);

            if ($status->requiresReviewer() && ($authorization->reviewed_by === null || $authorization->reviewed_at === null)) {
                throw OvertimeDecisionRefused::withoutAReviewer();
            }

            // PRD §7.4: a flagged day's data is not trustworthy enough to pay
            // from. Only approval is blocked — an objection stays reachable, so
            // a supervisor can still refuse hours nobody can vouch for.
            if ($status === OvertimeAuthorizationStatus::Approved) {
                $flags = $authorization->workday?->anomalyFlags() ?? [];

                if ($flags !== []) {
                    throw OvertimeDecisionRefused::withUnresolvedAnomalies($flags);
                }
            }
        });
    }

    /**
     * Open (or return) the pending authorisation for a computed day, snapshotting
     * the engine's figure as the OHC this decision will be made against.
     *
     * Opening a record is not a decision and cannot become one: the row starts
     * pending, and the only paths out of that are {@see self::approve()} and
     * {@see self::object()}, both of which demand a person. A day whose figure
     * later moves is reported as needing re-review by
     * {@see Workday::overtimeNeedsReReview()} rather than being quietly
     * re-snapshotted here, so the figure an approver saw stays the figure the
     * record says they saw.
     *
     * @param  string|null  $requestedHours  The employee's OHR, under Mode A.
     */
    public static function openFor(Workday $workday, ?string $requestedHours = null): self
    {
        return static::firstOrCreate(
            ['workday_id' => $workday->id],
            [
                'organization_id' => $workday->organization_id,
                'user_id' => $workday->user_id,
                'date' => $workday->date,
                'calculated_hours' => $workday->calculated_overtime,
                'requested_hours' => $requestedHours,
                'status' => OvertimeAuthorizationStatus::Pending,
            ],
        );
    }

    /**
     * Authorise this day's overtime.
     *
     * @param  User  $reviewer  The person deciding. Required — there is no other signature.
     * @param  string|null  $authorizedHours  The OHA as `HH:MM:SS`; defaults to authorising the calculated figure in full.
     * @param  string|null  $reason  Mandatory over a legal cap (KOL-41) or without a covering pacto (KOL-42).
     */
    public function approve(User $reviewer, ?string $authorizedHours = null, ?string $reason = null): self
    {
        $this->assertSameOrganizationAs($reviewer);

        $authorized = Duration::tryFrom($authorizedHours ?? $this->calculated_hours) ?? Duration::zero();

        $this->forceFill([
            'status' => OvertimeAuthorizationStatus::Approved,
            'authorized_hours' => $authorized->toTimeString(),
            'final_hours' => $this->finalHoursFor($authorized)->toTimeString(),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reason' => $reason,
        ])->save();

        return $this;
    }

    /**
     * Refuse this day's overtime. The hours worked are not erased — they stay
     * readable as unauthorised, which is the whole point of recording the
     * objection rather than deleting the record.
     *
     * @param  User  $reviewer  The person deciding. Required — there is no other signature.
     * @param  string  $reason  Why. An objection without one is unanswerable to the employee.
     */
    public function object(User $reviewer, string $reason): self
    {
        $this->assertSameOrganizationAs($reviewer);

        $this->forceFill([
            'status' => OvertimeAuthorizationStatus::Objected,
            'authorized_hours' => Duration::zero()->toTimeString(),
            'final_hours' => Duration::zero()->toTimeString(),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reason' => $reason,
        ])->save();

        return $this;
    }

    /**
     * What payroll is owed for this day: the lesser of what was authorised and
     * what was actually calculated from the marks, so an approver cannot grant
     * hours nobody worked and worked hours nobody granted are not paid.
     *
     * The requested figure (OHR) is recorded but deliberately absent from the
     * comparison — a day authorised for more than the employee asked for, and
     * worked in full, pays the authorised amount. KOL-46 owns the rest of this
     * rule, including the legal-cap ceiling of KOL-41.
     */
    private function finalHoursFor(Duration $authorized): Duration
    {
        return Duration::min($authorized, Duration::tryFrom($this->calculated_hours)) ?? Duration::zero();
    }

    /**
     * How many of this day's hours are payable. Zero unless a human approved
     * them: pending and objected records answer the question without the caller
     * having to remember to check the status first.
     */
    public function authorizedOvertime(): Duration
    {
        return $this->isApproved()
            ? Duration::tryFrom($this->final_hours) ?? Duration::zero()
            : Duration::zero();
    }

    /**
     * Hours the employee worked beyond what was authorised. On a pending or
     * objected day that is the whole calculated figure; on a partially
     * authorised one it is the remainder. Never merged into the payable total,
     * and never dropped — this is the number KOL-24 makes visually prominent.
     */
    public function unauthorizedOvertime(): Duration
    {
        return (Duration::tryFrom($this->calculated_hours) ?? Duration::zero())
            ->minus($this->authorizedOvertime());
    }

    public function isPending(): bool
    {
        return $this->status === OvertimeAuthorizationStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === OvertimeAuthorizationStatus::Approved;
    }

    public function isObjected(): bool
    {
        return $this->status === OvertimeAuthorizationStatus::Objected;
    }

    /**
     * The only records payroll may read (PRD §7.7).
     *
     * @param  Builder<OvertimeAuthorization>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', OvertimeAuthorizationStatus::Approved);
    }

    /**
     * The supervisors' queue of PRD §7.5.
     *
     * @param  Builder<OvertimeAuthorization>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', OvertimeAuthorizationStatus::Pending);
    }

    /**
     * @param  Builder<OvertimeAuthorization>  $query
     */
    public function scopeObjected(Builder $query): void
    {
        $query->where('status', OvertimeAuthorizationStatus::Objected);
    }

    /**
     * Constrain to authorisations whose worked date falls within the inclusive
     * range — the payroll period selection.
     *
     * @param  Builder<OvertimeAuthorization>  $query
     */
    public function scopeBetweenDates(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @return BelongsTo<Workday, $this>
     */
    public function workday(): BelongsTo
    {
        return $this->belongsTo(Workday::class);
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
            throw OvertimeDecisionRefused::byAnotherTenant();
        }
    }
}
