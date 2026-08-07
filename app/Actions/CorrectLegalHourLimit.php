<?php

namespace App\Actions;

use App\Models\LegalHourLimit;
use App\Models\Workday;
use App\Services\LegalHourLimits;
use App\Services\WorkdayCalculator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only path by which an already-recorded legal-limit version changes, and
 * the reason the model refuses a plain update.
 *
 * A version is normally never edited: a change in the law appends a new one.
 * What this exists for is the other case — a figure or an effective date typed
 * in wrong. That is not a new rule, it is the same rule stated incorrectly, so
 * appending would be a lie about the timeline. Editing it, though, silently
 * changes what every day judged against it should have reported.
 *
 * So the correction is explicit and it pays its own bill: it demands a written
 * reason, and it recalculates every day the version was applied to before it
 * returns. There is no way to get the edit without the recalculation.
 */
class CorrectLegalHourLimit
{
    public function __construct(
        private WorkdayCalculator $calculator,
        private LegalHourLimits $limits,
    ) {}

    /**
     * Apply the correction and recalculate the days it affects.
     *
     * @param  array<string, mixed>  $figures  the corrected values, keyed by {@see LegalHourLimit::FIGURES}
     * @param  string  $reason  why the recorded version was wrong
     * @return int the number of computed days recalculated
     */
    public function handle(LegalHourLimit $version, array $figures, string $reason): int
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Correcting a legal working-hour limit requires a written reason.');
        }

        $corrections = Arr::only($figures, LegalHourLimit::FIGURES);

        if ($corrections === []) {
            throw new InvalidArgumentException('A correction must change at least one of the recorded figures.');
        }

        return DB::transaction(function () use ($version, $corrections, $reason): int {
            $previous = Arr::only($version->getOriginal(), array_keys($corrections));

            LegalHourLimit::whileCorrecting(function () use ($version, $corrections): void {
                $version->forceFill($corrections)->save();
            });

            // The resolver memoises per date, and an effective_from correction
            // moves which dates this version even covers.
            $this->limits->flush();

            $recalculated = $this->recalculateAffectedDays($version);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($version)
                ->event('corrected')
                ->withProperties([
                    'old' => $previous,
                    'attributes' => $corrections,
                    'reason' => $reason,
                    'recalculated_workdays' => $recalculated,
                ])
                ->log('Legal working-hour limit corrected');

            return $recalculated;
        });
    }

    /**
     * Recompute every day stamped with this version, restamping each against
     * what its own date resolves to now — a corrected `effective_from` can move
     * a day out of this version's range entirely, and leaving it stamped with a
     * version that no longer covers it is the drift the stamp exists to catch.
     */
    private function recalculateAffectedDays(LegalHourLimit $version): int
    {
        $recalculated = 0;

        $version->workdays()
            ->chunkById(200, function ($workdays) use (&$recalculated): void {
                foreach ($workdays as $workday) {
                    /** @var Workday $workday */
                    $this->calculator->recalculateWorkday($workday);
                    $recalculated++;
                }
            });

        return $recalculated;
    }
}
