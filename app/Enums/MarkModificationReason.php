<?php

namespace App\Enums;

use App\Models\MarkModification;

/**
 * Why an attendance mark is being modified or added. Captured on each
 * {@see MarkModification} for the audit trail required of the
 * electronic attendance book.
 */
enum MarkModificationReason: string
{
    case MarkForgotten = 'mark_forgotten';
    case MarkIncorrect = 'mark_incorrect';
    case SystemError = 'system_error';
    case ShiftChange = 'shift_change';
    case JustifiedMissingTime = 'justified_missing_time';
    case InsideToleranceTime = 'inside_tolerance_time';
    case Other = 'other';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.mark_modifications.reasons.'.$this->value);
    }

    /**
     * All reasons as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $reason): array => ['value' => $reason->value, 'label' => $reason->label()],
            self::cases(),
        );
    }
}
