<?php

namespace App\Enums;

use App\Models\OvertimeAuthorization;

/**
 * The human decision on a day's overtime (PRD §7.5, reworked by KOL-80):
 * `pending`, `approved` or `revoked`.
 *
 * **There is no expired or lapsed case, and that is the design.** The PRD is
 * explicit that overtime is *"never auto-approved by timeout — an ungoverned
 * record simply isn't exported, it's not assumed approved by default"*. Under
 * KOL-80 that silence is no longer even a stored row: a day nobody has acted
 * on has no {@see OvertimeAuthorization} at all, and {@see self::Pending} is
 * only the instant between {@see OvertimeAuthorization::openFor()} and the
 * decision that follows it in the same request — never a state a queue lists
 * or a supervisor waits in.
 *
 * **There is no `objected` case either.** KOL-80 dropped it: silence on a day
 * nobody approved is sufficient refusal, so the only way out of `pending` is
 * {@see self::Approved}. An approved record can later move to
 * {@see self::Revoked} — the row stays, with who revoked it, when, and why —
 * but nothing decided ever goes back to unapproved-with-a-reason.
 *
 * Distinct from {@see OvertimeCalculationState}, which is what the engine may
 * conclude on its own and has no approved case at all.
 *
 * @see OvertimeAuthorization
 */
enum OvertimeAuthorizationStatus: string
{
    /** The instant between opening a record and deciding it. Never listed, never waited in. */
    case Pending = 'pending';

    /** A human authorised these hours. The only status payroll may read. */
    case Approved = 'approved';

    /** A previously approved record whose authorisation was withdrawn. Preserved, not deleted. */
    case Revoked = 'revoked';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.overtime.authorization_statuses.'.$this->value);
    }

    /**
     * A shared, semantic badge tone for the status so the UI colours are
     * decided once here rather than per component.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Pending => 'warning',
            self::Revoked => 'destructive',
        };
    }

    /**
     * Whether reaching this status required somebody to act. Every terminal
     * status does, which is why the record refuses to persist in any of them
     * without a reviewer attached.
     */
    public function requiresReviewer(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * All statuses as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
