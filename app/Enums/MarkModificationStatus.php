<?php

namespace App\Enums;

/**
 * The review state of a request to modify (or add) an attendance mark on a
 * workday. Pending modifications flag the workday for HR attention.
 */
enum MarkModificationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.mark_modifications.statuses.'.$this->value);
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
