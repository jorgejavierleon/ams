<?php

namespace App\Services;

use App\Exceptions\MissingLegalHourLimit;
use App\Models\LegalHourLimit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Resolves Chile's legal working-hour limits for a date.
 *
 * **Every method requires the date it is asked about, and there is deliberately
 * no `current()`, no `latest()` and no argument that defaults to today.** The
 * bug this guards against always looks the same: someone reaches for the newest
 * version when they meant the applicable one, and a period that was reported
 * one way in August starts reporting another way in 2028 because the law
 * changed in between. If the only way to obtain a limit is to say which date it
 * is for, that cannot happen by accident — the property holds by construction
 * rather than by everyone remembering.
 *
 * Bound as a scoped singleton, so resolving the same date repeatedly within a
 * request (the shift list asks once per row) costs one query.
 */
class LegalHourLimits
{
    /**
     * Versions already resolved this request, keyed by date.
     *
     * @var array<string, LegalHourLimit>
     */
    private array $resolved = [];

    /**
     * The limits in force on the given date.
     *
     * @throws MissingLegalHourLimit when no version covers the date
     */
    public function on(CarbonInterface $date): LegalHourLimit
    {
        $key = $date->toDateString();

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $limit = LegalHourLimit::query()
            ->where('effective_from', '<=', $key)
            ->orderByDesc('effective_from')
            ->first();

        if ($limit === null) {
            throw MissingLegalHourLimit::for($date);
        }

        return $this->resolved[$key] = $limit;
    }

    /**
     * The limits governing the whole week the given date falls in.
     *
     * A week is Monday–Sunday (the ISO week the DT-certified daily report
     * already totals by, {@see Reports\DailyReportService}), and the week is
     * judged against the version in force on its **Monday** — so a limit change
     * landing mid-week takes effect from the following Monday.
     *
     * Two of the three Ley 21.561 steps land mid-week (26 April 2024 was a
     * Friday, 26 April 2028 is a Wednesday), so this is not hypothetical. The
     * weekly cap is a budget spent across the week: applying a newly lowered
     * ceiling from Wednesday would retroactively turn hours already lawfully
     * worked on Monday and Tuesday into an excess, against a limit that did not
     * exist when they were worked. Taking the week's opening rule means both
     * parties know the ceiling before the week starts, and no already-worked
     * hour ever changes character. The daily caps have no such problem and are
     * resolved per day by {@see self::on()}.
     *
     * @throws MissingLegalHourLimit when no version covers the week's Monday
     */
    public function forWeekOf(CarbonInterface $date): LegalHourLimit
    {
        return $this->on(self::weekStart($date));
    }

    /**
     * The Monday opening the week the given date belongs to.
     */
    public static function weekStart(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);
    }

    /**
     * Drop the memoised versions. Needed after a correction rewrites one, so
     * the recalculation that follows reads the corrected figures.
     */
    public function flush(): void
    {
        $this->resolved = [];
    }
}
