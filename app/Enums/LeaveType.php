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
