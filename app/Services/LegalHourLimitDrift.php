<?php

namespace App\Services;

use App\Models\LegalHourLimit;
use App\Models\Workday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds computed days whose stamped limit version disagrees with what their own
 * date resolves to now.
 *
 * The stamp never drives a figure — the figures come from resolving the day's
 * date at calculation time. What the stamp is for is exactly this: if a day
 * carries version 2 and its date now resolves to version 3, either a version
 * was appended with an effective date reaching back over days already computed,
 * or a day was computed against the wrong rule. Both are worth knowing about
 * and neither is visible without the stamp.
 */
class LegalHourLimitDrift
{
    /**
     * The stamped days that no longer agree with their date.
     *
     * @return Collection<int, Workday>
     */
    public function detect(): Collection
    {
        return $this->query()->with('legalHourLimit')->get();
    }

    public function exists(): bool
    {
        return $this->query()->exists();
    }

    /**
     * Stamped days whose version is not the one their date resolves to.
     *
     * Built by walking the version timeline rather than with a correlated
     * subquery per row: there are a handful of versions and millions of days,
     * so each version contributes one date range to check.
     *
     * @return Builder<Workday>
     */
    private function query(): Builder
    {
        $versions = LegalHourLimit::query()->chronological()->get();

        $query = Workday::query()->whereNotNull('legal_hour_limit_id');

        if ($versions->isEmpty()) {
            // Nothing to agree with: every stamp is orphaned.
            return $query;
        }

        return $query->where(function (Builder $query) use ($versions): void {
            // A day before the first version has no applicable rule at all, so
            // whatever it is stamped with, it drifted.
            $query->whereDate('date', '<', $versions->first()->effective_from);

            foreach ($versions as $index => $version) {
                $next = $versions->get($index + 1);

                $query->orWhere(function (Builder $query) use ($version, $next): void {
                    $query->whereDate('date', '>=', $version->effective_from)
                        ->when($next, fn (Builder $query) => $query->whereDate('date', '<', $next->effective_from))
                        ->where('legal_hour_limit_id', '!=', $version->id);
                });
            }
        });
    }
}
