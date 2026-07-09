<?php

namespace App\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Signals that the workdays for a set of employees within a date range need to
 * be recalculated. Fired by observers whenever the data a workday derives from
 * changes (e.g. a shift assignment starts or ends).
 *
 * The listener that consumes this event and runs the WorkdayCalculator belongs
 * to the M3 attendance work; until then the event has no listeners and is a
 * harmless no-op, keeping the observer contract intact ahead of time.
 */
class WorkdaysRecalculationNeeded
{
    use Dispatchable;

    /**
     * @param  Collection<int, int>  $userIds
     */
    public function __construct(
        public Collection $userIds,
        public DateTimeInterface $startDate,
        public DateTimeInterface $endDate,
        public bool $shouldQueue = true,
    ) {}
}
