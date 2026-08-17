<?php

namespace App\Services\Overtime;

use App\Exceptions\RestDayBalanceRefused;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRestDayBalance;
use App\Models\OvertimeRestDayConsumption;
use App\Models\User;
use App\Support\Duration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Accrual, consumption and expiry for rest-day compensation balances
 * (KOL-47, Código del Trabajo art. 32 §4).
 *
 * The exchange rate and the expiry window both come straight from the
 * statute, not from a guess: "por cada hora extraordinaria corresponderá una
 * hora y media de feriado" fixes {@see self::REST_HOURS_PER_OVERTIME_HOUR} at
 * 1.5, and "dentro de los seis meses siguientes al ciclo en que se
 * originaron" fixes the window at six months from the day the overtime was
 * worked. See KOL-47's task notes for the full citation.
 */
class RestDayBalanceService
{
    private const REST_HOURS_PER_OVERTIME_HOUR = 1.5;

    private const EXPIRY_MONTHS = 6;

    /**
     * Accrue the balance a rest-day-compensated approval produces. Called
     * once, from {@see OvertimeAuthorization::approve()} — the unique
     * constraint on `overtime_authorization_id` makes a second accrual for
     * the same record a database error rather than a silent double-credit.
     */
    public function accrueFor(OvertimeAuthorization $authorization): OvertimeRestDayBalance
    {
        $accrued = Duration::tryFrom($authorization->final_hours) ?? Duration::zero();
        $restSeconds = (int) round($accrued->seconds * self::REST_HOURS_PER_OVERTIME_HOUR);
        $accrualDate = Carbon::parse($authorization->date);

        return OvertimeRestDayBalance::create([
            'organization_id' => $authorization->organization_id,
            'user_id' => $authorization->user_id,
            'overtime_authorization_id' => $authorization->id,
            'accrued_hours' => $accrued->toTimeString(),
            'rest_hours' => Duration::fromSeconds($restSeconds)->toTimeString(),
            'consumed_hours' => '00:00:00',
            'accrual_date' => $accrualDate,
            'expiry_date' => $accrualDate->copy()->addMonths(self::EXPIRY_MONTHS),
        ]);
    }

    /**
     * The total rest-hours an employee can still spend: every unexpired
     * line's remainder, summed. Expired lines are deliberately excluded —
     * their remainder is no longer spendable balance, it is payable instead.
     */
    public function availableBalance(User $user): Duration
    {
        return OvertimeRestDayBalance::query()
            ->forUser($user->id)
            ->unexpired()
            ->get()
            ->reduce(
                fn (Duration $carry, OvertimeRestDayBalance $balance): Duration => Duration::fromSeconds(
                    $carry->seconds + $balance->remaining()->seconds,
                ),
                Duration::zero(),
            );
    }

    /**
     * Draw `$hours` of rest-day balance for `$user`, oldest-expiring line
     * first, so hours closest to lapsing are spent before ones with runway
     * left. Splits across as many lines as it takes and writes one
     * {@see OvertimeRestDayConsumption} per line drawn from — KOL-47 AC #2's
     * traceability, "which accrued hours paid for this day off."
     *
     * @return Collection<int, OvertimeRestDayConsumption>
     *
     * @throws RestDayBalanceRefused when the employee does not have enough unexpired balance.
     */
    public function consume(User $user, Duration $hours, ?string $note = null, ?User $registeredBy = null, ?Carbon $consumedOn = null): Collection
    {
        $available = $this->availableBalance($user);

        if ($hours->seconds > $available->seconds) {
            throw RestDayBalanceRefused::insufficientBalance($hours, $available);
        }

        $lines = OvertimeRestDayBalance::query()
            ->forUser($user->id)
            ->unexpired()
            ->get()
            ->filter(fn (OvertimeRestDayBalance $balance): bool => ! $balance->isFullyConsumed());

        $remaining = $hours->seconds;
        $consumptions = new Collection;

        foreach ($lines as $line) {
            if ($remaining <= 0) {
                break;
            }

            $lineRemaining = $line->remaining()->seconds;
            $take = min($lineRemaining, $remaining);

            $consumptions->push(OvertimeRestDayConsumption::create([
                'organization_id' => $line->organization_id,
                'overtime_rest_day_balance_id' => $line->id,
                'hours' => Duration::fromSeconds($take)->toTimeString(),
                'consumed_on' => ($consumedOn ?? Carbon::today())->toDateString(),
                'note' => $note,
                'registered_by' => $registeredBy?->id,
            ]));

            $line->forceFill([
                'consumed_hours' => Duration::fromSeconds(
                    (Duration::tryFrom($line->consumed_hours) ?? Duration::zero())->seconds + $take,
                )->toTimeString(),
            ])->save();

            $remaining -= $take;
        }

        return $consumptions;
    }

    /**
     * Stamp every unswept line past its `expiry_date` as expired (KOL-47 AC
     * #3), regardless of whether anything remains on it — the window is
     * closed either way, and a fully-consumed line has nothing left to
     * convert. Intended for the daily scheduled sweep.
     *
     * @return int Lines swept.
     */
    public function sweepExpired(): int
    {
        $lines = OvertimeRestDayBalance::query()->pastExpiry()->get();

        foreach ($lines as $line) {
            $line->markExpired();
        }

        return $lines->count();
    }
}
