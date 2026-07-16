<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Resolves whether a calendar date is a business day (not a weekend, not a
 * Chilean public holiday) and derives the next business day after one.
 *
 * Backs the Resolución 38, art. 41 c) rule: a mark correction may not be made
 * until the business day following the day being corrected. Holidays are read
 * through the {@see Holiday} model's global scope, so both official and the
 * current organization's own holidays count.
 */
class BusinessDayResolver
{
    /**
     * Whether the given date is a working day — a weekday that is not a holiday.
     */
    public function isBusinessDay(CarbonInterface $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! Holiday::query()
            ->whereDate('date', $date->toDateString())
            ->exists();
    }

    /**
     * The first business day strictly after the given date.
     */
    public function nextBusinessDay(CarbonInterface $date): CarbonInterface
    {
        $next = $date->copy()->addDay();

        while (! $this->isBusinessDay($next)) {
            $next = $next->addDay();
        }

        return $next;
    }

    /**
     * Whether a workday dated `$workdayDate` may be corrected on `$on`
     * (defaults to today): the action is only allowed from the business day
     * following the corrected day onwards (art. 41 c).
     */
    public function correctionAllowed(CarbonInterface $workdayDate, ?CarbonInterface $on = null): bool
    {
        $on ??= Carbon::today();

        return $on->copy()->startOfDay()
            ->greaterThanOrEqualTo($this->nextBusinessDay($workdayDate)->copy()->startOfDay());
    }
}
