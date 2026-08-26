<?php

namespace App\Support;

use App\Enums\ReportPeriodType;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * A payroll report period: a calendar month, or one of its two quincenas
 * (KOL-19 AC #2). Resolves to the concrete `[start, end]` date range every
 * report, the aggregation service (KOL-13) and the integrity check (KOL-14)
 * consume identically.
 */
final readonly class ReportPeriod
{
    public function __construct(
        public int $year,
        public int $month,
        public ReportPeriodType $type,
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid month: {$month}.");
        }
    }

    public function start(): Carbon
    {
        return match ($this->type) {
            ReportPeriodType::Month, ReportPeriodType::FirstFortnight => Carbon::create($this->year, $this->month, 1)->startOfDay(),
            ReportPeriodType::SecondFortnight => Carbon::create($this->year, $this->month, 16)->startOfDay(),
        };
    }

    public function end(): Carbon
    {
        return match ($this->type) {
            ReportPeriodType::Month => Carbon::create($this->year, $this->month, 1)->endOfMonth(),
            ReportPeriodType::FirstFortnight => Carbon::create($this->year, $this->month, 15)->endOfDay(),
            ReportPeriodType::SecondFortnight => Carbon::create($this->year, $this->month, 1)->endOfMonth(),
        };
    }
}
