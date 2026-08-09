<?php

namespace App\Jobs;

use App\Enums\OvertimeCalculationState;
use App\Listeners\RecalculateWorkdays;
use App\Services\WorkdayCalculator;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs the overtime calculation engine for one organization over one date or a
 * range of them (PRD §7.2: *"Runs as an async job after shift close-out"*).
 *
 * **It cannot emit a payable hour.** The job writes through
 * {@see WorkdayCalculator}, whose write set contains no approval column and
 * whose verdict is an {@see OvertimeCalculationState} — an enum with no approved
 * case. The highest thing this job can say about a day is that it is pending
 * review; turning that into hours anyone is paid for takes the authorisation
 * record of PRD §8, which this job neither writes nor knows about.
 *
 * **Re-running is safe and expected.** Each date is upserted on the
 * `(user_id, date)` unique index, so a backfill over a month that overlaps days
 * already processed updates them in place and leaves identical figures where
 * nothing moved. Re-running is in fact the normal case: a corrected mark, a
 * newly approved leave or a reassigned shift all arrive here through
 * {@see RecalculateWorkdays}.
 *
 * Deliberately **not** `ShouldBeUnique`. Uniqueness would silently drop a
 * dispatch that overlapped one in flight, and every dispatch here stands for a
 * change to the underlying data — the dropped one would be a correction nobody
 * ever processed. Idempotency is the upsert's job; suppressing work is not.
 */
class CalculateOvertime implements ShouldQueue
{
    use Queueable;

    public readonly CarbonImmutable $startDate;

    public readonly CarbonImmutable $endDate;

    /**
     * @param  int  $organizationId  The only tenant this run may touch.
     * @param  DateTimeInterface|string  $startDate  First date to process.
     * @param  DateTimeInterface|string|null  $endDate  Last date, inclusive. Defaults to a single day.
     * @param  array<int, int>|null  $userIds  Restrict the run to these employees; null processes the whole organization.
     * @param  bool  $onlyComputedDays  Recompute only days already rolled up, rather than backfilling days nobody ever computed.
     */
    public function __construct(
        public readonly int $organizationId,
        DateTimeInterface|string $startDate,
        DateTimeInterface|string|null $endDate = null,
        public readonly ?array $userIds = null,
        public readonly bool $onlyComputedDays = false,
    ) {
        $this->startDate = CarbonImmutable::parse($startDate)->startOfDay();

        $end = CarbonImmutable::parse($endDate ?? $startDate)->startOfDay();

        // A backwards range is a caller mistake, not an instruction to process
        // nothing in silence: clamp to the single start date.
        $this->endDate = $end->lessThan($this->startDate) ? $this->startDate : $end;
    }

    public function handle(WorkdayCalculator $calculator): void
    {
        for ($date = $this->startDate; $date->lessThanOrEqualTo($this->endDate); $date = $date->addDay()) {
            $this->onlyComputedDays
                ? $calculator->recalculateComputedDate($date, $this->organizationId, $this->userIds)
                : $calculator->calculateDate($date, $this->organizationId, $this->userIds);
        }
    }

    /**
     * Tags so a backfill is legible in Horizon/Telescope without opening the
     * payload.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'overtime',
            'organization:'.$this->organizationId,
            'dates:'.$this->startDate->toDateString().'..'.$this->endDate->toDateString(),
        ];
    }
}
