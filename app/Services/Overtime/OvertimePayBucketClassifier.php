<?php

namespace App\Services\Overtime;

use App\Enums\OvertimeCompensationType;
use App\Enums\OvertimeDayType;
use App\Enums\OvertimePayBucket;
use App\Models\OvertimeAuthorization;
use App\Models\Workday;
use App\Support\Duration;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Sorts a period's overtime into the buckets a payroll summary needs
 * (KOL-12): what is payable now split by {@see OvertimePayBucket}, what is
 * authorised but paid in time off instead of money, and what nobody
 * authorised at all.
 *
 * Read-side only — nothing here writes to {@see Workday} or
 * {@see OvertimeAuthorization}, so recalculating a day never changes a
 * previously classified figure for a day already approved: {@see
 * OvertimeExportDataset} reads the frozen `final_hours`, not a live
 * recomputation (KOL-46's decision-stamp guarantee already makes that hold).
 *
 * Deliberately does not read `workdays.extra_time` (the legacy Resolución 38
 * figure) — per the KOL-12 task's own m-2 rescoping note, this classifies the
 * approved-only export dataset from KOL-49 plus KOL-46's unauthorised
 * remainder, both built on `calculated_overtime` (OHC), not `extra_time`.
 */
class OvertimePayBucketClassifier
{
    public function __construct(
        private OvertimeExportDataset $dataset,
    ) {}

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, OvertimePayBucketBreakdown>
     */
    public function forPeriod(CarbonInterface $start, CarbonInterface $end, array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        $payable = $this->payableSeconds($start, $end, $userIds);
        [$restDays, $unauthorized] = $this->workdaySeconds($start, $end, $userIds);

        return collect($userIds)->mapWithKeys(fn (int $userId): array => [
            $userId => new OvertimePayBucketBreakdown(
                userId: $userId,
                ordinaryDayHours: Duration::fromSeconds($payable[$userId][OvertimePayBucket::OrdinaryDay->value] ?? 0),
                sundayOrHolidayHours: Duration::fromSeconds($payable[$userId][OvertimePayBucket::SundayOrHoliday->value] ?? 0),
                compensatedInRestDaysHours: Duration::fromSeconds($restDays[$userId] ?? 0),
                unauthorizedHours: Duration::fromSeconds($unauthorized[$userId] ?? 0),
            ),
        ]);
    }

    /**
     * Payable hours (PRD §7.7, KOL-49), bucketed by day type. Structurally
     * approved-and-paid-in-money only, so a pending, revoked or still-active
     * rest-day-compensated hour cannot appear here — {@see
     * self::workdaySeconds()} accounts for those instead.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, int>> seconds per user id per {@see OvertimePayBucket} value
     */
    private function payableSeconds(CarbonInterface $start, CarbonInterface $end, array $userIds): array
    {
        $seconds = [];

        foreach ($this->dataset->forPeriod($start, $end, $userIds) as $line) {
            $bucket = $this->bucketFor($line->dayType);
            $seconds[$line->userId][$bucket->value] = ($seconds[$line->userId][$bucket->value] ?? 0) + $line->hours->seconds;
        }

        return $seconds;
    }

    private function bucketFor(OvertimeDayType $dayType): OvertimePayBucket
    {
        return $dayType === OvertimeDayType::Weekday
            ? OvertimePayBucket::OrdinaryDay
            : OvertimePayBucket::SundayOrHoliday;
    }

    /**
     * The two dispositions {@see OvertimeExportDataset} cannot answer,
     * because it only ever reads paid-in-money records: hours authorised but
     * compensated in rest days (KOL-47), and hours nobody authorised at all
     * (KOL-46). Both are read straight off {@see Workday} so a day nobody has
     * even opened a decision for — no {@see OvertimeAuthorization} row —
     * still counts its full calculated figure as unauthorised, per {@see
     * Workday::unauthorizedOvertime()}.
     *
     * @param  list<int>  $userIds
     * @return array{0: array<int, int>, 1: array<int, int>} seconds per user id: [compensatedInRestDays, unauthorized]
     */
    private function workdaySeconds(CarbonInterface $start, CarbonInterface $end, array $userIds): array
    {
        $restDays = [];
        $unauthorized = [];

        $workdays = Workday::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('calculated_overtime')
            ->with('overtimeAuthorization')
            ->get();

        foreach ($workdays as $workday) {
            $authorization = $workday->overtimeAuthorization;

            if ($authorization instanceof OvertimeAuthorization
                && $authorization->isApproved()
                && $authorization->compensation_type === OvertimeCompensationType::RestDays) {
                $restDays[$workday->user_id] = ($restDays[$workday->user_id] ?? 0) + $authorization->authorizedOvertime()->seconds;
            }

            $unauthorized[$workday->user_id] = ($unauthorized[$workday->user_id] ?? 0) + $workday->unauthorizedOvertime()->seconds;
        }

        return [$restDays, $unauthorized];
    }
}
