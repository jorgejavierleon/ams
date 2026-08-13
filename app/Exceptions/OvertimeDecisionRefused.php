<?php

namespace App\Exceptions;

use App\Enums\AnomalyFlagReason;
use App\Models\OvertimeAuthorization;
use App\Services\Overtime\OvertimeCapBreach;
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

    /**
     * PRD §7.4: an anomaly means the day's underlying data is not trustworthy
     * enough to pay from, and it blocks the record from reaching `approved`
     * until a human has looked at it. Objecting a flagged day is unaffected —
     * refusing hours nobody can vouch for is exactly what that path is for.
     *
     * @param  array<int, AnomalyFlagReason>  $reasons
     */
    public static function withUnresolvedAnomalies(array $reasons): self
    {
        $labels = implode(', ', array_map(fn (AnomalyFlagReason $reason): string => $reason->label(), $reasons));

        return new self(
            "An overtime authorisation cannot be approved while its workday carries unresolved anomaly flags: {$labels}. Correcting the underlying data clears the flag and unblocks approval."
        );
    }

    /**
     * PRD §7.3, Resolución 38 art. 45.2: a legal cap never blocks the excess
     * itself, but approving beyond one is only defensible on audit with a
     * reason attached. No justification, no approval.
     */
    public static function withoutJustification(OvertimeCapBreach $breach): self
    {
        $labels = implode(', ', $breach->labels());

        return new self(
            "An overtime authorisation exceeding a legal cap ({$labels}) cannot be approved without a written justification. The excess itself is allowed; approving it unexplained is not."
        );
    }

    /**
     * Código del Trabajo art. 32, decision-1: the DT reality criterion holds
     * that hours worked with the employer's knowledge are overtime whether or
     * not a written pacto covers them, so a missing pacto is never a bar to
     * approval — it only demands the same audit trail a legal-cap breach does.
     */
    public static function withoutAuditTrail(): self
    {
        return new self(
            'An overtime authorisation with no pacto covering its worked date cannot be approved without a written justification. The absence of a pacto never blocks payment; approving it unexplained does.'
        );
    }
}
