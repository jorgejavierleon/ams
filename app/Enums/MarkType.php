<?php

namespace App\Enums;

/**
 * The two kinds of attendance punch an employee registers: entering (IN) and
 * leaving (OUT) work.
 */
enum MarkType: string
{
    case In = 'in';
    case Out = 'out';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.marks.types.'.$this->value);
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
