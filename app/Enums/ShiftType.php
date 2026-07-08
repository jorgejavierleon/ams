<?php

namespace App\Enums;

enum ShiftType: string
{
    case Fixed = 'fixed';
    case Rotational = 'rotational';
    case Cyclic = 'cyclic';
    case Biweekly = 'biweekly';
    case Exceptional = 'exceptional';
    case Partial = 'partial';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.shifts.types.'.$this->value);
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
