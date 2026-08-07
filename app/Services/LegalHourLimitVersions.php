<?php

namespace App\Services;

use App\Models\LegalHourLimit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * The only way a legal working-hour limit version comes into existence.
 *
 * A change in the law appends a version with its own effective date; nothing
 * about the versions already recorded moves. Reachable from the SaaS panel
 * only — no tenant-facing route, controller or job calls it, and the model
 * refuses a create that does not come through here.
 */
class LegalHourLimitVersions
{
    public function __construct(private LegalHourLimits $limits) {}

    /**
     * Append a version. Adding one never alters an existing row, so every
     * figure already calculated and reported keeps the answer it had.
     *
     * @param  array<string, mixed>  $figures  keyed by {@see LegalHourLimit::FIGURES}
     */
    public function add(array $figures): LegalHourLimit
    {
        $version = LegalHourLimit::whileAppending(
            fn (): LegalHourLimit => LegalHourLimit::query()->create(
                Arr::only($figures, LegalHourLimit::FIGURES)
            )
        );

        // A version added for a date already resolved this request would
        // otherwise keep answering with the version it superseded.
        $this->limits->flush();

        // An appended version changes what every employer in the system is
        // measured against from its effective date, so it is attributable to a
        // person and a moment for the same reason a correction is.
        activity()
            ->causedBy(Auth::user())
            ->performedOn($version)
            ->event('created')
            ->withProperties(['attributes' => [
                ...$version->only(LegalHourLimit::FIGURES),
                'effective_from' => $version->effective_from->toDateString(),
            ]])
            ->log('Legal working-hour limit version added');

        return $version;
    }
}
