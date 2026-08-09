<?php

namespace App\Listeners;

use App\Events\WorkdaysRecalculationNeeded;
use App\Jobs\CalculateOvertime;
use App\Models\User;
use App\Observers\LeaveObserver;
use App\Observers\ShiftAssignmentObserver;
use App\Services\WorkdayCalculator;
use Illuminate\Support\Collection;

/**
 * The consumer {@see WorkdaysRecalculationNeeded} was written for.
 *
 * The observers that raise it ({@see LeaveObserver},
 * {@see ShiftAssignmentObserver}) know an employee and a date range; the
 * calculation engine works one tenant at a time. This listener is the join
 * between the two: it resolves the affected employees' organizations and
 * dispatches one {@see CalculateOvertime} per tenant, so a range that somehow
 * spans two of them still never has one tenant's pass touch the other's rows.
 *
 * **Only days already computed are touched.** An approved leave or an edited
 * shift assignment says something about days the register has rolled up; it is
 * not an instruction to materialise every uncomputed day in its range — an
 * assignment backdated a month would otherwise quietly manufacture a month of
 * absences. Backfilling is a deliberate act and goes through the
 * `overtime:calculate` command.
 *
 * @see WorkdayCalculator::recalculateComputedDate()
 */
class RecalculateWorkdays
{
    public function handle(WorkdaysRecalculationNeeded $event): void
    {
        $this->organizationsOf($event->userIds)->each(
            function (Collection $userIds, int|string $organizationId) use ($event): void {
                $job = new CalculateOvertime(
                    organizationId: (int) $organizationId,
                    startDate: $event->startDate,
                    endDate: $event->endDate,
                    userIds: $userIds->all(),
                    onlyComputedDays: true,
                );

                // The event carries whether the caller can afford to wait: a
                // console backfill wants the work done before it reports, a
                // request does not.
                $event->shouldQueue ? dispatch($job) : dispatch_sync($job);
            },
        );
    }

    /**
     * The affected employees' ids, grouped by the organization they belong to.
     *
     * Read without the tenant scope on purpose: the recalculation runs from
     * observers and console commands alike, and the organization is taken from
     * the employee rather than from whoever happened to trigger the change.
     *
     * @param  Collection<int, int>  $userIds
     * @return Collection<int|string, Collection<int, int>>
     */
    private function organizationsOf(Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::withoutGlobalScopes()
            ->whereIn('id', $userIds->all())
            ->whereNotNull('organization_id')
            ->get(['id', 'organization_id'])
            ->groupBy('organization_id')
            ->map(fn (Collection $users): Collection => $users->pluck('id')->map(intval(...))->values());
    }
}
