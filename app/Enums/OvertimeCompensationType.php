<?php

namespace App\Enums;

/**
 * How approved overtime is compensated by default (Código del Trabajo art. 32
 * inciso 4, echoed by Resolución 38 art. 43, which requires both forms to be
 * available).
 *
 * `Payment` is the default because, absent a written agreement stating
 * otherwise, the law assumes the hours are paid in the payroll run; rest days
 * only apply when the parties agreed to them.
 */
enum OvertimeCompensationType: string
{
    case Payment = 'payment';
    case RestDays = 'rest_days';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.organization_settings.overtime_compensation_types.'.$this->value);
    }

    /**
     * All compensation types as value/label pairs for select inputs.
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
