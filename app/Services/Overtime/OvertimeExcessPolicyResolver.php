<?php

namespace App\Services\Overtime;

use App\Models\Workday;
use App\Services\OrganizationSettings;
use App\Services\WorkdayCalculator;

/**
 * The single seam that decides which excess policy a calculated day is judged
 * under. {@see WorkdayCalculator} asks this and nothing else — it never reads a
 * setting itself — so moving the decision from the tenant to the shift or to an
 * individual day is a change here and nowhere in the arithmetic.
 *
 * Answers come off the cached settings attributes array and are memoised per
 * organization for the life of the instance, so a whole day's chunked pass over
 * an organization costs no query per workday.
 */
class OvertimeExcessPolicyResolver
{
    /** @var array<int, OvertimeExcessPolicy> */
    private array $byOrganization = [];

    public function __construct(private OrganizationSettings $organizationSettings) {}

    /**
     * The policy for one candidate workday row — either the query row being
     * inserted or a persisted {@see Workday}; both carry an
     * `organization_id`.
     */
    public function for(object $workday): OvertimeExcessPolicy
    {
        $organizationId = $workday->organization_id ?? null;

        if (! is_int($organizationId)) {
            return OvertimeExcessPolicy::postShiftOnly();
        }

        return $this->byOrganization[$organizationId] ??= new OvertimeExcessPolicy(
            countsPreShiftExcess: $this->organizationSettings->overtimeCountsPreShiftExcess($organizationId),
        );
    }
}
