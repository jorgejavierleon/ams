<?php

namespace App\Enums;

enum LeaveType: string
{
    case Vacation = 'vacation_lead';
    case Medical = 'medical_lead';
    case Unpaid = 'unpaid_lead';
    case Paid = 'paid_lead';
    case Other = 'other_lead';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.leaves.types.'.$this->value);
    }

    /**
     * Hex color used to render this type on the leaves calendar and its legend.
     */
    public function color(): string
    {
        return match ($this) {
            self::Vacation => '#059669',
            self::Medical => '#dc2626',
            self::Unpaid => '#d97706',
            self::Paid => '#2563eb',
            self::Other => '#6b7280',
        };
    }

    /**
     * All types as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }

    /**
     * All types as value/label/color triples for the calendar legend.
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function legendOptions(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'color' => $type->color(),
            ],
            self::cases(),
        );
    }

    /**
     * Types an employee may request for themselves. Medical leaves are excluded
     * because the LeaveObserver auto-approves them, which would bypass approval.
     *
     * @return array<int, self>
     */
    public static function selfServiceCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type !== self::Medical,
        ));
    }

    /**
     * Self-requestable types as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function selfServiceOptions(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::selfServiceCases(),
        );
    }
}
