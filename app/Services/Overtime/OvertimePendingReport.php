<?php

namespace App\Services\Overtime;

use App\Models\OvertimeAuthorization;
use App\Models\Workday;
use App\Services\OrganizationSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The stale-overtime alert of PRD §12 (KOL-52): a shift excess nobody has
 * approved is not a neutral non-event under the DT's criterio de realidad
 * (Código del Trabajo art. 32) — past the organization's configured
 * threshold, it is legal exposure that HR needs to see, grouped by who is
 * responsible for it.
 *
 * The clock starts on the worked {@see Workday::$date}, not on any
 * {@see OvertimeAuthorization} row's timestamp — KOL-80 made "pending" a
 * read-only condition (no row, or one left undecided) rather than a state a
 * row is created in ahead of time, so the day itself is the only anchor a
 * stale count can be measured from.
 */
class OvertimePendingReport
{
    public function __construct(
        private OrganizationSettings $settings,
    ) {}

    public function thresholdDays(): int
    {
        return $this->settings->overtimePendingAlertThresholdDays();
    }

    /**
     * @return Builder<Workday>
     */
    public function staleWorkdaysQuery(?int $thresholdDays = null): Builder
    {
        $cutoff = Carbon::today()->subDays($thresholdDays ?? $this->thresholdDays());

        return Workday::query()
            ->needsOvertimeDecision()
            ->whereDate('date', '<=', $cutoff);
    }

    public function staleCount(?int $thresholdDays = null): int
    {
        return $this->staleWorkdaysQuery($thresholdDays)->count();
    }

    /**
     * How many days the single longest-unresolved stale record has been
     * waiting — the figure that makes a digest concrete ("38 days", not just
     * "some records"). Null when nothing is stale.
     */
    public function oldestDaysPending(?int $thresholdDays = null): ?int
    {
        return $this->staleSummary($thresholdDays)['oldest_days_pending'];
    }

    /**
     * {@see self::staleCount()} and {@see self::oldestDaysPending()} in a
     * single aggregate query — what {@see OvertimePendingAlertNotifier}
     * needs per organization on every scheduled run, without doubling the
     * database round-trips across every tenant.
     *
     * @return array{count: int, oldest_days_pending: int|null}
     */
    public function staleSummary(?int $thresholdDays = null): array
    {
        $row = $this->staleWorkdaysQuery($thresholdDays)
            ->selectRaw('COUNT(*) as stale_count, MIN(date) as oldest_date')
            ->first();

        $oldestDate = $row?->getAttribute('oldest_date');

        return [
            'count' => (int) ($row?->getAttribute('stale_count') ?? 0),
            'oldest_days_pending' => $oldestDate === null
                ? null
                : (int) Carbon::parse($oldestDate)->diffInDays(Carbon::today(), absolute: true),
        ];
    }

    /**
     * Days between the marked day and its decision, averaged across every
     * approved record — the PRD's second success metric, surfaced from the
     * same data rather than a separate aggregation. A single database-side
     * aggregate, so it stays bounded regardless of how many records exist.
     */
    public function averageResolutionDays(): ?float
    {
        $average = OvertimeAuthorization::query()
            ->approved()
            ->whereNotNull('reviewed_at')
            ->selectRaw('AVG(DATEDIFF(reviewed_at, date)) as average_days')
            ->value('average_days');

        return $average === null ? null : round((float) $average, 1);
    }
}
