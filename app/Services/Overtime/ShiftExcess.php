<?php

namespace App\Services\Overtime;

use App\Services\WorkdayCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The two shift excesses of one calculated day, per PRD §7.2:
 *
 * - **pre-shift excess** = `shift start − first mark`, positive values only
 * - **post-shift excess** = `last mark − shift end`, positive values only
 *
 * Neither is span-minus-scheduled-duration, which is a different number the
 * moment an employee arrives early. Both are held in whole seconds and rendered
 * to the second, because Resolución 38 art. 44 leaves no room for rounding that
 * favours either party — a report may round when it renders, the engine may not.
 *
 * The times are anchored to real instants rather than compared as clock times:
 * a shift whose end time is at or before its start time crosses midnight, so its
 * end lands on the day after the shift started. The excesses of such a shift —
 * and therefore its overtime — belong to the calendar day the shift *started*,
 * the usual Chilean reading of a jornada as one indivisible unit.
 *
 * Returns null rather than a zero when there is no basis to claim overtime at
 * all: no assigned shift, or only one of the two marks. Nothing is inferred.
 * {@see WorkdayCalculator} stores that null and leaves the day to be flagged.
 */
final readonly class ShiftExcess
{
    private function __construct(
        public int $preShiftSeconds,
        public int $postShiftSeconds,
    ) {}

    /**
     * Compute both excesses for a candidate workday row produced by
     * {@see WorkdayCalculator::getWorkdayQuery()}.
     */
    public static function forWorkdayRow(object $row): ?self
    {
        return self::for(
            markIn: $row->mark_in_at ?? null,
            markOut: $row->mark_out_at ?? null,
            shiftStartTime: $row->shift_start_time ?? null,
            shiftEndTime: $row->shift_end_time ?? null,
            date: $row->date,
        );
    }

    /**
     * @param  mixed  $markIn  The day's first mark, as a Carbon or datetime string.
     * @param  mixed  $markOut  The day's last mark, as a Carbon or datetime string.
     * @param  mixed  $shiftStartTime  Scheduled start, as an `HH:MM:SS` string.
     * @param  mixed  $shiftEndTime  Scheduled end, as an `HH:MM:SS` string.
     * @param  mixed  $date  The calendar day being computed.
     */
    public static function for(
        mixed $markIn,
        mixed $markOut,
        mixed $shiftStartTime,
        mixed $shiftEndTime,
        mixed $date,
    ): ?self {
        $markIn = self::toCarbon($markIn);
        $markOut = self::toCarbon($markOut);

        // No shift is no basis, and one mark is a day to flag, not to guess at.
        if ($shiftStartTime === null || $shiftEndTime === null || $markIn === null || $markOut === null) {
            return null;
        }

        $startOfDay = Carbon::parse($date)->startOfDay();
        $shiftStart = $startOfDay->copy()->setTimeFromTimeString(self::toTimeString($shiftStartTime));
        $shiftEnd = $startOfDay->copy()->setTimeFromTimeString(self::toTimeString($shiftEndTime));

        // An end at or before the start is a shift that runs past midnight.
        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd->addDay();
        }

        return new self(
            preShiftSeconds: self::positiveSecondsBetween($markIn, $shiftStart),
            postShiftSeconds: self::positiveSecondsBetween($shiftEnd, $markOut),
        );
    }

    /** Pre-shift excess as `HH:MM:SS`. */
    public function preShiftExcess(): string
    {
        return self::format($this->preShiftSeconds);
    }

    /** Post-shift excess as `HH:MM:SS`. */
    public function postShiftExcess(): string
    {
        return self::format($this->postShiftSeconds);
    }

    /**
     * The calculated overtime (OHC) the given policy makes of these excesses:
     * always the post-shift excess, plus the pre-shift excess only where the
     * organization counts early arrival.
     */
    public function calculatedOvertime(OvertimeExcessPolicy $policy): string
    {
        return self::format(
            $this->postShiftSeconds + ($policy->countsPreShiftExcess ? $this->preShiftSeconds : 0),
        );
    }

    /**
     * Whole seconds from $from to $to, clamped at zero — a mark on the right
     * side of its boundary is not a negative excess, it is no excess.
     */
    private static function positiveSecondsBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return max(0, (int) $from->diffInSeconds($to, absolute: false));
    }

    /**
     * `HH:MM:SS`, built by hand rather than through `gmdate()` so an excess of a
     * day or more reads as the hours it is instead of wrapping back to zero.
     */
    private static function format(int $seconds): string
    {
        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60,
        );
    }

    private static function toCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        return is_string($value) ? Carbon::parse($value) : null;
    }

    private static function toTimeString(mixed $value): string
    {
        return $value instanceof CarbonInterface
            ? $value->format('H:i:s')
            : (string) $value;
    }
}
