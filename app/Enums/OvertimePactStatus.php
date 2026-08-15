<?php

namespace App\Enums;

use App\Models\OvertimePact;

/**
 * The lifecycle of a *pacto de horas extraordinarias* (PRD §7.6): `active` or
 * `revoked`.
 *
 * **There is no stored expired case, and that mirrors
 * {@see OvertimeAuthorizationStatus}.** Expiry is date math, not a status:
 * whether a pact still covers a date is decided by comparing `end_date`
 * against that date at read time, in {@see OvertimePact::scopeCoveringDate()}.
 * A pact does not need anyone to notice it lapsed for the coverage check to
 * stop finding it — the same reasoning that keeps a lapsed case out of
 * {@see OvertimeAuthorizationStatus}. `revoked` is the only state that needs a
 * human, so it is the only one stored.
 *
 * @see OvertimePact
 */
enum OvertimePactStatus: string
{
    /** In force. Whether it still covers a given date is a date comparison, not this status. */
    case Active = 'active';

    /** Withdrawn by an admin before its term ran out. Kept, not deleted, as evidence of what was agreed. */
    case Revoked = 'revoked';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.overtime.pact_statuses.'.$this->value);
    }

    /**
     * The shadcn `Badge` variant used to colour the status pill.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'default',
            self::Revoked => 'outline',
        };
    }
}
