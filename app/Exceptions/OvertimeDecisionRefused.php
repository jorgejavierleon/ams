<?php

namespace App\Exceptions;

use App\Models\OvertimeAuthorization;
use RuntimeException;

/**
 * An overtime authorisation was asked to leave `pending` in a way that would
 * put payable hours behind nobody.
 *
 * Both refusals below are structural rather than procedural: they fire on the
 * model's write path, so a console command, a backfill or a future queue job
 * hits them exactly as a controller does.
 *
 * @see OvertimeAuthorization
 */
class OvertimeDecisionRefused extends RuntimeException
{
    /**
     * PRD §7.5: overtime is *never* auto-approved by timeout. A row can only
     * carry `approved` or `objected` with the person who decided it and the
     * moment they did, so no scheduler, cron or backfill can age a record into
     * being payable — an ungoverned record simply stays pending and is never
     * exported.
     */
    public static function withoutAReviewer(): self
    {
        return new self(
            'An overtime authorisation cannot be approved or objected without the user who decided it and when. Elapsed time is not a decision: a record nobody acts on stays pending.'
        );
    }

    /**
     * Tenant isolation at the moment it matters most. Reads are already
     * constrained by the organization scope; this covers the write, so a
     * reviewer resolved outside the scope — an id from a request body, a user
     * carried over from another session — cannot decide another employer's
     * hours.
     */
    public static function byAnotherTenant(): self
    {
        return new self(
            'An overtime authorisation can only be decided by a user of the same organization.'
        );
    }
}
