<?php

namespace App\Enums;

use App\Services\WorkdayCalculator;

/**
 * The computed daily attendance outcome for an employee, derived by
 * {@see WorkdayCalculator} from that day's marks, scheduled
 * shift and approved leaves.
 */
enum WorkdayStatus: string
{
    case Regular = 'regular';
    case Irregular = 'irregular';
    case Absent = 'absent';
    case Incomplete = 'incomplete';
    case Justified = 'justified';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.workdays.statuses.'.$this->value);
    }

    /**
     * A shared, semantic badge tone for the status so the UI colours are
     * decided once here rather than per component.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Regular, self::Justified => 'success',
            self::Irregular, self::Incomplete => 'warning',
            self::Absent => 'destructive',
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
