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
     * A shared, semantic badge tone for the status so the UI colours are
     * decided once here rather than per component.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Pending => 'warning',
            self::Declined => 'destructive',
        };
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
