<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;

/**
 * The result of {@see PayrollExportReadinessService::check()}: every
 * unresolved item found for an employee/period selection, or none at all
 * for a clean period (KOL-14 AC #4 — no warning, no extra confirmation step).
 */
final readonly class PayrollExportReadiness
{
    /**
     * @param  Collection<int, PayrollExportFinding>  $findings
     */
    public function __construct(
        public Collection $findings,
    ) {}

    public function isClean(): bool
    {
        return $this->findings->isEmpty();
    }

    /**
     * Whether an export against this selection needs an explicit user
     * confirmation before it may proceed (KOL-14 AC #3).
     */
    public function requiresConfirmation(): bool
    {
        return $this->findings->contains(fn (PayrollExportFinding $finding): bool => $finding->blocking());
    }

    /**
     * Findings grouped by employee, informational period-level findings (no
     * `userId`) grouped under key `0`.
     *
     * @return Collection<int, Collection<int, PayrollExportFinding>>
     */
    public function groupedByEmployee(): Collection
    {
        return $this->findings->groupBy(fn (PayrollExportFinding $finding): int => $finding->userId ?? 0);
    }
}
