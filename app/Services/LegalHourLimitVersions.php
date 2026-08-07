<?php

namespace App\Services;

use App\Models\LegalHourLimit;
use Illuminate\Support\Arr;

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

        return $version;
    }
}
