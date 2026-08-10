<?php

namespace App\Services\Overtime;

use App\Models\OvertimeAuthorization;
use App\Models\Workday;
use App\Services\LegalHourLimits;
use App\Support\Duration;
use Illuminate\Support\Carbon;

/**
 * Whether approving a day's overtime at its current payable figure would
 * exceed a legal ceiling (PRD §7.3): the daily and weekly overtime caps of
 * Código del Trabajo art. 31, and the combined ordinary-plus-extraordinary
 * ceilings.
 *
 * Every ceiling is resolved against the **worked date**, never today's, via
 * {@see LegalHourLimits}, so approving a day long after a limit changed is
 * still judged by the version in force when the hours were worked.
 *
 * The daily figure this evaluates is {@see OvertimeAuthorization::authorizedOvertime()}
 * - what is actually going to be paid (MIN of authorised and calculated) -
 * not the raw amount an approver typed in. A request for four hours on a day
 * that only produced one payable hour has not touched any ceiling; the four
 * hours were never going to be owed.
 */
class OvertimeCapEvaluator
{
    public function __construct(private LegalHourLimits $legalHourLimits) {}

    public function evaluate(OvertimeAuthorization $authorization): OvertimeCapBreach
    {
        $date = Carbon::parse($authorization->date);

        $dailyLimits = $this->legalHourLimits->on($date);
        $weeklyLimits = $this->legalHourLimits->forWeekOf($date);

        $proposed = $authorization->authorizedOvertime();
        $ordinary = $this->ordinaryHours($authorization->workday);
        $dailyTotalSeconds = $ordinary->seconds + $proposed->seconds;

        [$weekOtherOvertimeSeconds, $weekOtherTotalSeconds] = $this->weekOtherApprovedSeconds($authorization, $date);

        return new OvertimeCapBreach(
            dailyOvertime: $proposed->seconds > $this->hoursToSeconds($dailyLimits->max_overtime_daily_hours),
            weeklyOvertime: ($weekOtherOvertimeSeconds + $proposed->seconds) > $this->hoursToSeconds($weeklyLimits->max_overtime_weekly_hours),
            dailyTotal: $dailyTotalSeconds > $this->hoursToSeconds($dailyLimits->max_total_daily_hours),
            weeklyTotal: ($weekOtherTotalSeconds + $dailyTotalSeconds) > $this->hoursToSeconds($weeklyLimits->max_total_weekly_hours),
        );
    }

    /**
     * The hours actually worked within the ordinary/scheduled portion of the
     * day - the worked span less the shift excess that produced the overtime
     * figure - so daily/weekly totals compare like against like rather than
     * double-counting the excess.
     */
    private function ordinaryHours(?Workday $workday): Duration
    {
        if ($workday === null) {
            return Duration::zero();
        }

        $worked = Duration::tryFrom($workday->worked_time) ?? Duration::zero();
        $overtime = Duration::tryFrom($workday->calculated_overtime) ?? Duration::zero();

        return $worked->minus($overtime);
    }

    /**
     * The seconds already spoken for elsewhere in the same Monday-Sunday week
     * (PRD §7.3: "accounts for hours already approved earlier in the same
     * week"), by every other Approved authorisation for this employee - the
     * one being decided is excluded so a re-approval never counts itself
     * twice.
     *
     * @return array{0: int, 1: int} [overtime seconds, ordinary+overtime seconds]
     */
    private function weekOtherApprovedSeconds(OvertimeAuthorization $authorization, Carbon $date): array
    {
        $weekStart = LegalHourLimits::weekStart($date);
        $weekEnd = $weekStart->addDays(6);

        $others = OvertimeAuthorization::query()
            ->with('workday')
            ->where('organization_id', $authorization->organization_id)
            ->where('user_id', $authorization->user_id)
            ->approved()
            ->betweenDates($weekStart, $weekEnd)
            ->when($authorization->exists, fn ($query) => $query->where('id', '!=', $authorization->id))
            ->get();

        $overtimeSeconds = 0;
        $totalSeconds = 0;

        foreach ($others as $other) {
            $overtime = $other->authorizedOvertime();
            $ordinary = $this->ordinaryHours($other->workday);

            $overtimeSeconds += $overtime->seconds;
            $totalSeconds += $ordinary->seconds + $overtime->seconds;
        }

        return [$overtimeSeconds, $totalSeconds];
    }

    private function hoursToSeconds(float $hours): int
    {
        return (int) round($hours * 3600);
    }
}
