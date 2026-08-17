<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\Overtime\RestDayBalanceService;
use App\Support\Duration;
use Database\Factories\OvertimeRestDayBalanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One accrual of rest-day compensation, born from exactly one approved
 * {@see OvertimeAuthorization} (KOL-47, Código del Trabajo art. 32 §4).
 *
 * The balance currency is `rest_hours`, not `accrued_hours`: the statute
 * fixes the exchange at "una hora y media de feriado" per overtime hour, so
 * `rest_hours` is `accrued_hours` times 1.5, computed once at accrual and
 * never re-derived. `accrued_hours` stays on the row purely as the audit
 * trail back to what was actually approved.
 *
 * Six months from `accrual_date`, unused `rest_hours` must be paid rather
 * than lost (the statute again: "corresponderá su pago"). This row is never
 * deleted on that expiry — {@see self::markExpired()} stamps it instead, so
 * an audit can see both what was accrued and that it lapsed unused. See
 * {@see RestDayBalanceService} for accrual,
 * consumption and the expiry sweep.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property int $overtime_authorization_id
 * @property string $accrued_hours
 * @property string $rest_hours
 * @property string $consumed_hours
 * @property Carbon $accrual_date
 * @property Carbon $expiry_date
 * @property Carbon|null $expired_at
 */
class OvertimeRestDayBalance extends Model
{
    /** @use HasFactory<OvertimeRestDayBalanceFactory> */
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accrual_date' => 'date',
            'expiry_date' => 'date',
            'expired_at' => 'datetime',
        ];
    }

    /**
     * Rest-hours still spendable on this line: what was accrued less what
     * consumption records have drawn from it. Never negative — a line cannot
     * be over-consumed, {@see RestDayBalanceService::consume()}
     * refuses to draw more than this.
     */
    public function remaining(): Duration
    {
        return (Duration::tryFrom($this->rest_hours) ?? Duration::zero())
            ->minus(Duration::tryFrom($this->consumed_hours));
    }

    public function isFullyConsumed(): bool
    {
        return $this->remaining()->isZero();
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null;
    }

    /**
     * Whether today is past this line's `expiry_date` and it has not yet been
     * swept — the population {@see RestDayBalanceService::sweepExpired()}
     * acts on.
     */
    public function isPastExpiry(): bool
    {
        return $this->expired_at === null && Carbon::today()->gt($this->expiry_date);
    }

    /**
     * Stamp the remainder as expired-and-payable. Never deleted: the row
     * stays visible as evidence of what lapsed unused (AC #3), and its
     * remaining hours become the payable amount KOL-49 must union in
     * alongside payment-compensated authorizations.
     */
    public function markExpired(): self
    {
        $this->forceFill(['expired_at' => now()])->save();

        return $this;
    }

    /**
     * The OT-hour amount now owed as payment because it expired unconsumed
     * (Código del Trabajo art. 32 §4: "corresponderá su pago"), converting
     * back out of the 1.5x rest-hours currency. Zero unless {@see self::isExpired()}.
     */
    public function payableFromExpiry(): Duration
    {
        if (! $this->isExpired()) {
            return Duration::zero();
        }

        return Duration::fromSeconds((int) round($this->remaining()->seconds / 1.5));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<OvertimeAuthorization, $this>
     */
    public function authorization(): BelongsTo
    {
        return $this->belongsTo(OvertimeAuthorization::class, 'overtime_authorization_id');
    }

    /**
     * @return HasMany<OvertimeRestDayConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(OvertimeRestDayConsumption::class);
    }

    /**
     * @param  Builder<OvertimeRestDayBalance>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Lines not yet swept as expired, oldest expiry first — the FIFO order
     * {@see RestDayBalanceService::consume()} draws
     * from, so the hours closest to lapsing are spent first.
     *
     * @param  Builder<OvertimeRestDayBalance>  $query
     */
    public function scopeUnexpired(Builder $query): void
    {
        $query->whereNull('expired_at')->orderBy('expiry_date');
    }

    /**
     * @param  Builder<OvertimeRestDayBalance>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expired_at');
    }

    /**
     * Unswept lines whose `expiry_date` has passed — the sweep's candidate
     * set, before the per-row remaining-balance check the service still has
     * to make in PHP (a zero-remaining line needs no expiry stamp).
     *
     * @param  Builder<OvertimeRestDayBalance>  $query
     */
    public function scopePastExpiry(Builder $query): void
    {
        $query->whereNull('expired_at')->whereDate('expiry_date', '<', Carbon::today());
    }
}
