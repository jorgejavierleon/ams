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
}
