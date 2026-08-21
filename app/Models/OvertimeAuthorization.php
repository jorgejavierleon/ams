<?php

namespace App\Models;

use App\Enums\OvertimeAuthorizationStatus;
use App\Enums\OvertimeCompensationType;
use App\Exceptions\OvertimeDecisionRefused;
use App\Http\Controllers\WorkdayController;
use App\Models\Concerns\BelongsToOrganization;
use App\Services\Overtime\OvertimeCapEvaluator;
use App\Services\Overtime\RestDayBalanceService;
use App\Support\Duration;
use Carbon\CarbonInterface;
use Database\Factories\OvertimeAuthorizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property OvertimeCompensationType $compensation_type
 * @property int|null $revoked_by
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
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
            'compensation_type' => OvertimeCompensationType::class,
            'date' => 'date',
            'reviewed_at' => 'datetime',
            'revoked_at' => 'datetime',
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
            // from. Only approval is blocked — silence (never approving) is
            // always reachable, so a supervisor can still withhold hours
            // nobody can vouch for without this check standing in the way.
            if ($status === OvertimeAuthorizationStatus::Approved) {
                $flags = $authorization->workday?->anomalyFlags() ?? [];

                if ($flags !== []) {
                    throw OvertimeDecisionRefused::withUnresolvedAnomalies($flags);
                }

                // PRD §7.3, Resolución 38 art. 45.2: a legal cap never blocks the
                // excess, but approving beyond one is only defensible on audit
                // with a reason attached.
                $breach = app(OvertimeCapEvaluator::class)->evaluate($authorization);

                if ($breach->any() && trim((string) $authorization->reason) === '') {
                    throw OvertimeDecisionRefused::withoutJustification($breach);
                }

                // Art. 32, decision-1: the DT reality criterion holds these
                // hours are overtime whether or not a written pacto covers
                // them, so a missing pacto never blocks approval — it only
                // demands the same audit trail a legal-cap breach does.
                if ($authorization->overtime_pact_id === null && trim((string) $authorization->reason) === '') {
                    throw OvertimeDecisionRefused::withoutAuditTrail();
                }
            }
        });
    }

    /**
     * Open (or return) the authorisation for a computed day, snapshotting the
     * engine's figure as the OHC this decision will be made against.
     *
     * KOL-80: nothing in app/ calls this ahead of a decision any more — a day
     * nobody has acted on simply has no row (see {@see Workday::authorizedOvertime()}).
     * The only callers are {@see WorkdayController::approveOvertime()}
     * and {@see WorkdayController::bulkDecideOvertime()},
     * which open the record and immediately decide it with {@see self::approve()}
     * in the same request — the transient `pending` status is never a state a
     * queue lists or a supervisor waits in. A day whose figure later moves is
     * reported as needing re-review by {@see Workday::overtimeNeedsReReview()}
     * rather than being quietly re-snapshotted here, so the figure an approver
     * saw stays the figure the record says they saw.
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
     * Resolves and snapshots the pacto covering this record's worked `date`
     * (KOL-42 AC #4) — not today's — so a decision made long after the fact is
     * still judged against the agreement in force when the hours were worked.
     * The pacto has no say in {@see OvertimeCompensationType} (KOL-47 AC #8):
     * that is the approver's own choice, per record, honoured only when
     * {@see User::$overtime_rest_day_eligible} is set on the employee's
     * profile — a standing eligibility flag, independent of any pacto.
     * Omitting the choice, or the employee not being eligible, always resolves
     * to {@see OvertimeCompensationType::Payment}; nothing can make rest-day
     * compensation reachable for an ineligible employee. Rest-day
     * compensation accrues a balance via {@see RestDayBalanceService} once the
     * row is saved; payment does not.
     *
     * @param  User  $reviewer  The person deciding. Required — there is no other signature.
     * @param  string|null  $authorizedHours  The OHA as `HH:MM:SS`; defaults to authorising the calculated figure in full.
     * @param  string|null  $reason  Mandatory over a legal cap (KOL-41) or without a covering pacto (KOL-42).
     * @param  OvertimeCompensationType|null  $compensationType  The approver's choice; null means payment.
     *
     * @throws OvertimeDecisionRefused when rest-day compensation is requested for an ineligible employee.
     */
    public function approve(User $reviewer, ?string $authorizedHours = null, ?string $reason = null, ?OvertimeCompensationType $compensationType = null): self
    {
        $this->assertSameOrganizationAs($reviewer);

        if ($compensationType === OvertimeCompensationType::RestDays && ! $this->user->overtime_rest_day_eligible) {
            throw OvertimeDecisionRefused::notEligibleForRestDayCompensation();
        }

        $resolvedCompensationType = $compensationType ?? OvertimeCompensationType::Payment;

        $authorized = Duration::tryFrom($authorizedHours ?? $this->calculated_hours) ?? Duration::zero();
        $pact = OvertimePact::coveringDateFor($this->user_id, $this->date, $this->organization_id);

        $this->forceFill([
            'status' => OvertimeAuthorizationStatus::Approved,
            'authorized_hours' => $authorized->toTimeString(),
            'final_hours' => $this->finalHoursFor($authorized)->toTimeString(),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reason' => $reason,
            'overtime_pact_id' => $pact?->id,
            'compensation_type' => $resolvedCompensationType,
        ])->save();

        $this->stampWorkdayDecision();

        if ($resolvedCompensationType === OvertimeCompensationType::RestDays && ! $this->authorizedOvertime()->isZero()) {
            app(RestDayBalanceService::class)->accrueFor($this);
        }

        return $this;
    }

    /**
     * Withdraw a previously approved record's authorisation (KOL-80). The row
     * is kept, not deleted: {@see self::authorizedOvertime()} answers zero
     * once this runs (it is non-zero only while {@see self::isApproved()}),
     * and the approval's own `reviewed_by`/`reviewed_at`/`reason` stay
     * untouched — who revoked it and why is recorded as a separate fact
     * alongside them, not a rewrite of the original decision.
     *
     * @param  User  $reviewer  The person revoking. Required — there is no other signature.
     * @param  string  $reason  Why. A revocation without one is unanswerable to the employee.
     *
     * @throws OvertimeDecisionRefused when the record is not currently approved.
     */
    public function revoke(User $reviewer, string $reason): self
    {
        $this->assertSameOrganizationAs($reviewer);

        if (! $this->isApproved()) {
            throw OvertimeDecisionRefused::withoutApproval();
        }

        $this->forceFill([
            'status' => OvertimeAuthorizationStatus::Revoked,
            'revoked_by' => $reviewer->id,
            'revoked_at' => now(),
            'revoked_reason' => $reason,
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
     * worked in full, pays the authorised amount. A missing calculated figure
     * is excluded rather than treated as zero, via {@see Duration::min()}, so
     * a day decided before the engine ever ran is not floored to nothing.
     */
    private function finalHoursFor(Duration $authorized): Duration
    {
        return Duration::min($authorized, Duration::tryFrom($this->calculated_hours)) ?? Duration::zero();
    }

    /**
     * Freeze the OHC this decision was made against onto the day's
     * {@see Workday}, so a later recalculation that moves the engine's figure
     * is visible through {@see Workday::overtimeNeedsReReview()} instead of
     * silently repricing an already-decided day. The columns exist for
     * exactly this (KOL-39) but stay untouched by the engine itself — this is
     * the one write path allowed to set them.
     */
    private function stampWorkdayDecision(): void
    {
        $this->workday()->update([
            'overtime_decided_at' => $this->reviewed_at,
            'overtime_decided_value' => $this->calculated_hours,
        ]);
    }

    /**
     * How many of this day's hours are payable. Zero unless a human approved
     * them: a pending or revoked record answers the question without the
     * caller having to remember to check the status first.
     */
    public function authorizedOvertime(): Duration
    {
        return $this->isApproved()
            ? Duration::tryFrom($this->final_hours) ?? Duration::zero()
            : Duration::zero();
    }

    /**
     * Hours the employee worked beyond what was authorised. On a pending or
     * revoked day that is the whole calculated figure; on a partially
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

    public function isRevoked(): bool
    {
        return $this->status === OvertimeAuthorizationStatus::Revoked;
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
     * KOL-47 AC #4: the structural exclusion. An approved record compensated
     * in rest days can never satisfy this scope — not today, not after
     * consumption, not after expiry. There is no code path that flips a row's
     * own `compensation_type` back to payment; an expired-unconsumed balance
     * becomes payable through {@see OvertimeRestDayBalance::payableFromExpiry()}
     * instead, a distinct source KOL-49's export must additionally union in.
     *
     * @param  Builder<OvertimeAuthorization>  $query
     */
    public function scopeExportable(Builder $query): void
    {
        $query->approved()->where('compensation_type', OvertimeCompensationType::Payment);
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
    public function scopeRevoked(Builder $query): void
    {
        $query->where('status', OvertimeAuthorizationStatus::Revoked);
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
     * The rest-day accrual this record produced, when compensated that way
     * (KOL-47). Absent for every payment-compensated record.
     *
     * @return HasOne<OvertimeRestDayBalance, $this>
     */
    public function restDayBalance(): HasOne
    {
        return $this->hasOne(OvertimeRestDayBalance::class);
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
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
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
