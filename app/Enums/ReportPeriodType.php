<?php

namespace App\Enums;

/**
 * The pay-period shape RF-1 requires (PRD §5.1): Chilean PYMEs pay either
 * monthly or by quincena, so every payroll report's period selector must
 * offer both rather than only an arbitrary date range (KOL-19 AC #2).
 */
enum ReportPeriodType: string
{
    case Month = 'month';
    case FirstFortnight = 'first_fortnight';
    case SecondFortnight = 'second_fortnight';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.payroll_reports.filters.period_types.'.$this->value);
    }

    /**
     * All types as value/label pairs for select inputs.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
