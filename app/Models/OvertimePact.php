<?php

namespace App\Models;

use App\Enums\OvertimePactStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\OvertimePactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A *pacto de horas extraordinarias* (PRD §7.6, Código del Trabajo art. 32):
 * an employee's written agreement to work overtime, transitory and capped at
 * three months, renewable by creating a new agreement rather than extending
 * this one.
 *
 * Nothing here decides whether overtime gets approved — a missing or expired
 * pacto is a flag on {@see OvertimeAuthorization}, never a bar (see
 * backlog/decisions/decision-1). It does not decide compensation type either
 * (KOL-47): that is a standing per-employee eligibility flag
 * ({@see User::$overtime_rest_day_eligible}), chosen per record by whoever
 * approves the overtime, independent of whether a pacto covers the date.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property OvertimePactStatus $status
 * @property Carbon|null $expiry_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'start_date', 'end_date', 'status'])]
class OvertimePact extends Model
{
    /** @use HasFactory<OvertimePactFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OvertimePactStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'expiry_notified_at' => 'datetime',
        ];
    }

    /**
     * Withdraw the agreement. Never deleted — the row is the evidence of what
     * was agreed and when, which is the entire reason it exists.
     */
    public function revoke(): self
    {
        $this->update(['status' => OvertimePactStatus::Revoked]);

        return $this;
    }

    /**
     * Undo a revocation. Reinstates the same row rather than requiring a new
     * one, for the case where the pacto was revoked by mistake.
     */
    public function activate(): self
    {
        $this->update(['status' => OvertimePactStatus::Active]);

        return $this;
    }

    /**
     * Stamp the near-expiry alert as sent (KOL-42 AC #3), so
     * {@see self::scopeNearingExpiry()} never finds this pacto again. Not
     * mass-assignable on purpose — it is written from exactly one place, the
     * scheduled notifier, never from a form.
     */
    public function markExpiryNotified(): self
    {
        $this->forceFill(['expiry_notified_at' => now()])->save();

        return $this;
    }

    /**
     * The agreement covering a worked date for an employee, judged against
     * that date and not today's (KOL-42 AC #4): approving in September an hour
     * worked in August consults the pact in force in August, even if it has
     * since expired or been superseded by a renewal.
     */
    public static function coveringDateFor(int $userId, Carbon|string $date, ?int $organizationId = null): ?self
    {
        return static::query()
            ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->where('user_id', $userId)
            ->active()
            ->coveringDate($date)
            ->first();
    }

    /**
     * @param  Builder<OvertimePact>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', OvertimePactStatus::Active);
    }

    /**
     * Constrain to agreements whose [start_date, end_date] range contains the
     * given date.
     *
     * @param  Builder<OvertimePact>  $query
     */
    public function scopeCoveringDate(Builder $query, Carbon|string $date): void
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    /**
     * Active agreements due to expire within the given number of days from
     * today — the population the near-expiry alert (AC #3) notifies about.
     *
     * @param  Builder<OvertimePact>  $query
     */
    public function scopeNearingExpiry(Builder $query, int $withinDays): void
    {
        $today = Carbon::today();

        $query->active()
            ->whereNull('expiry_notified_at')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $today->copy()->addDays($withinDays));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
