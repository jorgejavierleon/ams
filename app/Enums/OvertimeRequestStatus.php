<?php

namespace App\Enums;

use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRequest;

/**
 * The supervisor's decision on an employee's Mode A overtime request
 * (PRD §7.1, KOL-45). Distinct from {@see OvertimeAuthorizationStatus}: a
 * request is the ask, made before the hours are worked, and never itself
 * produces a payable hour — only an approved {@see OvertimeAuthorization}
 * does, once the day is actually worked and calculated.
 *
 * @see OvertimeRequest
 */
enum OvertimeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.overtime.requests.statuses.'.$this->value);
    }

    /**
     * A shared, semantic badge tone so the UI colours are decided once here
     * rather than per component.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Pending => 'warning',
            self::Rejected => 'destructive',
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
