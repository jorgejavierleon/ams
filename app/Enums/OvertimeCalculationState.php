<?php

namespace App\Enums;

use App\Jobs\CalculateOvertime;
use App\Models\Workday;

/**
 * Everything the overtime calculation engine is allowed to conclude about a day.
 *
 * **The absent cases are the point.** PRD §7.2 requires that the engine *"never
 * writes directly to an approved state"* and that its output *"can reach pending
 * review at most"*. That is not a convention here: this enum has no approved,
 * authorised or payable case, `Workday::$overtime_state` casts to it, and
 * {@see CalculateOvertime} writes nothing else — so there is no value a
 * refactor, a backfill or a console command could hand the engine that would
 * produce a payable hour. Passing one throws a `ValueError` at the cast.
 *
 * This is deliberately *not* the `pending | approved | objected` state machine
 * of PRD §7.5. That one governs a human decision and lives on the authorisation
 * record (KOL-11), a separate row the engine cannot write at all. Keeping the
 * two apart is what makes the guarantee structural rather than procedural: the
 * engine's vocabulary simply does not contain the word.
 *
 * @see Workday
 */
enum OvertimeCalculationState: string
{
    /**
     * The day gives nothing to review: no basis to compute overtime at all (no
     * assigned shift, or a single mark), or a computed excess of zero. Distinct
     * from a null column, which means the engine has not looked at the day yet.
     */
    case NotApplicable = 'not_applicable';

    /**
     * A positive calculated overtime (OHC) awaiting a human decision. The
     * highest state the engine can reach.
     */
    case PendingReview = 'pending_review';

    /**
     * The state a computed day is in, given the overtime the engine derived for
     * it. Null overtime means no basis; a zero excess means nothing to pay.
     */
    public static function forCalculatedOvertime(?string $calculatedOvertime): self
    {
        return $calculatedOvertime === null || $calculatedOvertime === '00:00:00'
            ? self::NotApplicable
            : self::PendingReview;
    }

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.overtime.calculation_states.'.$this->value);
    }

    /**
     * A shared, semantic badge tone so the UI colours are decided once here
     * rather than per component.
     */
    public function badge(): string
    {
        return match ($this) {
            self::PendingReview => 'warning',
            self::NotApplicable => 'secondary',
        };
    }
}
