<?php

namespace App\Enums;

enum LeaveHalfDayType: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.leaves.half_day_types.'.$this->value);
    }

    /**
     * All half-day types as value/label pairs for select inputs.
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
