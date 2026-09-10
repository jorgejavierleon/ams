<?php

namespace App\Services\Overtime;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\OvertimePendingOvertimeAlert;
use App\Support\CurrentOrganization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * The periodic side of PRD §12 (KOL-52): every organization with a shift
 * excess sitting undecided past its own configured threshold gets a digest
 * sent to whoever manages overtime there. An organization with nothing stale
 * is left untouched — no per-record dedup, since this is meant to keep
 * reminding for as long as the condition holds, not to fire once and stop.
 */
class OvertimePendingAlertNotifier
{
    public function __construct(
        private OvertimePendingReport $report,
    ) {}

    /**
     * @return int Organizations notified.
     */
    public function notifyStale(): int
    {
        $notified = 0;

        foreach ($this->organizationIds() as $organizationId) {
            CurrentOrganization::runAs($organizationId, function () use (&$notified, $organizationId): void {
                $threshold = $this->report->thresholdDays();
                $summary = $this->report->staleSummary($threshold);

                if ($summary['count'] === 0) {
                    return;
                }

                Notification::send(
                    $this->recipients($organizationId),
                    new OvertimePendingOvertimeAlert($summary['count'], $threshold, $summary['oldest_days_pending'] ?? $threshold),
                );

                $notified++;
            });
        }

        return $notified;
    }

    /**
     * @return array<int, int>
     */
    private function organizationIds(): array
    {
        return Organization::withoutGlobalScopes()->pluck('id')->map(intval(...))->all();
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(int $organizationId): Collection
    {
        return User::query()
            ->permission('Manage:OvertimeAuthorization')
            ->where('organization_id', $organizationId)
            ->get();
    }
}
