<?php

namespace App\Exceptions;

use App\Actions\CorrectLegalHourLimit;
use RuntimeException;

/**
 * A write was attempted against a legal working-hour limit version outside the
 * two paths that are allowed to make one.
 *
 * Versions are append-only. Editing the 42-hour row to say 40 destroys the past
 * exactly as a hardcoded constant does, only less visibly: every period already
 * reported against it would quietly start reporting something else. A genuine
 * mistake in a version is corrected through
 * {@see CorrectLegalHourLimit}, which recalculates every day the
 * version was applied to instead of leaving them stamped with a rule that no
 * longer says what it said.
 */
class LegalHourLimitIsAppendOnly extends RuntimeException
{
    public static function cannotUpdate(): self
    {
        return new self(
            'Legal working-hour limit versions are append-only. Add a new version for a change in the law, or use CorrectLegalHourLimit to fix a mistaken one and recalculate the days it affected.'
        );
    }

    public static function cannotDelete(): self
    {
        return new self(
            'Legal working-hour limit versions cannot be deleted: calculated days are stamped with the version they were judged against.'
        );
    }

    public static function cannotCreate(): self
    {
        return new self(
            'Legal working-hour limit versions must be added through LegalHourLimitVersions::add(), which is reachable only from the SaaS panel.'
        );
    }
}
