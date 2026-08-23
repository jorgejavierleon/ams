<?php

namespace App\Services\Overtime;

use App\Enums\OvertimeDayType;
use App\Models\Holiday;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRestDayBalance;
use App\Models\User;
use App\Support\Duration;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The one dataset the payroll export, and the reports built on top of it
 * (KOL-12, KOL-13, KOL-24), may read (PRD §7.7).
 *
 * **Structurally, not conventionally, approved-only.** Every line comes from
 * {@see OvertimeAuthorization::scopeExportable()} — `approved` status *and*
 * payment compensation — or from a {@see OvertimeRestDayBalance} that has
 * lapsed unconsumed. There is no third path into {@see self::forPeriod()}'s
 * result, so a pending or revoked record, or a rest-day-compensated one still
 * within its spendable window, cannot appear here by construction rather than
 * by a `where` a future caller could omit.
 *
 * **Two sources, per KOL-47's note on this task.** Rest-day-compensated hours
 * never reach payroll through the authorization row — Código del Trabajo art.
 * 32 §4 makes that exclusion permanent, even after expiry. But an expired,
 * unconsumed remainder is not forfeited either: the statute requires it be
 * paid "dentro de la remuneración del respectivo periodo", so it becomes a
 * second, distinct payable line, sourced from the balance itself and dated by
 * its `expiry_date` — the day the obligation to pay arises — rather than the
 * original workday, which is why the same employee can have two lines dated
 * differently for what was ultimately one worked day.
 *
 * **Day type describes the work, not the payment.** For an expiry-sourced
 * line, {@see OvertimeDayType} is still resolved from the *original* workday
 * date (via the balance's source {@see OvertimeAuthorization}), because that
 * is the day whose nature (weekday/Sunday/holiday) KOL-12 needs to classify
 * the hour into its legal pay bucket — the expiry date only says when the
 * money is owed.
 */
class OvertimeExportDataset
{
    /**
     * @param  list<int>  $userIds
     * @return Collection<int, OvertimeExportLine>
     */
    public function forPeriod(CarbonInterface $start, CarbonInterface $end, array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        $authorizations = OvertimeAuthorization::query()
            ->exportable()
            ->whereIn('user_id', $userIds)
            ->betweenDates($start, $end)
            ->with('user')
            ->get();

        $expiredBalances = OvertimeRestDayBalance::query()
            ->whereIn('user_id', $userIds)
            ->expired()
            ->whereBetween('expiry_date', [$start->toDateString(), $end->toDateString()])
            ->with(['user', 'authorization'])
            ->get()
            ->filter(fn (OvertimeRestDayBalance $balance): bool => ! $balance->payableFromExpiry()->isZero());

        $holidays = $this->holidaysFor($authorizations->pluck('date')
            ->concat($expiredBalances->map(fn (OvertimeRestDayBalance $balance): CarbonInterface => $balance->authorization->date)));

        return $authorizations
            ->map(fn (OvertimeAuthorization $authorization): OvertimeExportLine => $this->fromAuthorization($authorization, $holidays))
            ->concat($expiredBalances->map(fn (OvertimeRestDayBalance $balance): OvertimeExportLine => $this->fromExpiredBalance($balance, $holidays)))
            ->values();
    }

    /**
     * @param  Collection<string, Holiday>  $holidays
     */
    private function fromAuthorization(OvertimeAuthorization $authorization, Collection $holidays): OvertimeExportLine
    {
        return new OvertimeExportLine(
            userId: $authorization->user_id,
            employeeRut: $this->rutFor($authorization->user),
            date: $authorization->date,
            hours: Duration::tryFrom($authorization->final_hours) ?? Duration::zero(),
            dayType: $this->dayTypeFor($authorization->date, $holidays),
            pactReference: $authorization->overtime_pact_id,
            approvedBy: $authorization->reviewed_by,
            approvedAt: $authorization->reviewed_at,
        );
    }

    /**
     * @param  Collection<string, Holiday>  $holidays
     */
    private function fromExpiredBalance(OvertimeRestDayBalance $balance, Collection $holidays): OvertimeExportLine
    {
        $authorization = $balance->authorization;

        return new OvertimeExportLine(
            userId: $balance->user_id,
            employeeRut: $this->rutFor($balance->user),
            date: $balance->expiry_date,
            hours: $balance->payableFromExpiry(),
            dayType: $this->dayTypeFor($authorization->date, $holidays),
            pactReference: $authorization->overtime_pact_id,
            approvedBy: $authorization->reviewed_by,
            approvedAt: $authorization->reviewed_at,
        );
    }

    private function rutFor(?User $user): ?string
    {
        return $user->formatted_rut ?? $user->rut;
    }

    /**
     * @param  Collection<string, Holiday>  $holidays
     */
    private function dayTypeFor(CarbonInterface $date, Collection $holidays): OvertimeDayType
    {
        if ($holidays->has($date->toDateString())) {
            return OvertimeDayType::Holiday;
        }

        return $date->isSunday() ? OvertimeDayType::Sunday : OvertimeDayType::Weekday;
    }

    /**
     * Every holiday covering a date this call actually needs, in one query —
     * the period's dates plus, for expiry-sourced lines, the original workday
     * dates that can fall outside the period itself.
     *
     * @param  Collection<int, CarbonInterface>  $dates
     * @return Collection<string, Holiday>
     */
    private function holidaysFor(Collection $dates): Collection
    {
        $dates = $dates->map(fn (CarbonInterface $date): string => $date->toDateString())->unique()->values();

        if ($dates->isEmpty()) {
            return collect();
        }

        return Holiday::query()
            ->whereIn('date', $dates)
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->format('Y-m-d'));
    }
}
