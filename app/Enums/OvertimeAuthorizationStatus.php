<?php

namespace App\Enums;

use App\Models\OvertimeAuthorization;

/**
 * The human decision on a day's overtime (PRD §7.5): `pending`, `approved` or
 * `objected`.
 *
 * **There is no expired or lapsed case, and that is the design.** The PRD is
 * explicit that overtime is *"never auto-approved by timeout — an ungoverned
 * record simply isn't exported, it's not assumed approved by default"*. So a
 * record nobody acts on stays {@see self::Pending} for as long as it takes, and
 * the export reads {@see self::Approved} only. The absence of a fourth case is
 * what stops elapsed time from being mistaken for consent.
 *
 * This is the opposite reading of the same silence from
 * {@see MarkModificationStatus}, where the employee's opposition window *does*
 * consolidate on timeout (Resolución 38 art. 40 d). There, silence confirms a
 * correction the employer already made; here, silence would create a payment
 * obligation nobody agreed to.
 *
 * Distinct from {@see OvertimeCalculationState}, which is what the engine may
 * conclude on its own and has no approved case at all.
 *
 * @see OvertimeAuthorization
 */
enum OvertimeAuthorizationStatus: string
{
    /** Awaiting a decision. Never exported, never assumed approved. */
    case Pending = 'pending';

    /** A human authorised these hours. The only status payroll may read. */
    case Approved = 'approved';

    /** A human refused these hours. The worked time stays visible as unauthorised. */
    case Objected = 'objected';

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
            self::Objected => 'destructive',
        };
    }

    /**
     * Whether reaching this status required somebody to act. Both terminal
     * statuses do, which is why the record refuses to persist in either of them
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
