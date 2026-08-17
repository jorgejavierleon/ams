<?php

namespace App\Enums;

use App\Models\OvertimeAuthorization;
use App\Models\OvertimePact;

/**
 * How an approved overtime record is compensated (PRD §7.6 closing note,
 * Código del Trabajo art. 32): payment in the payroll run, or additional rest
 * days accrued to a balance (KOL-47).
 *
 * Reintroduced here after KOL-56 removed the tenant-wide setting of the same
 * shape: art. 43 of Resolución 38 requires the system to *offer* both modes
 * but fixes payment as the fallback absent a written agreement, which makes
 * this a property of the worker's {@see OvertimePact}, never an organization
 * preference. {@see OvertimeAuthorization::approve()} resolves it from the
 * pact covering the worked date and falls back to {@see self::Payment} when
 * none applies — a fallback nothing in the application can override.
 */
enum OvertimeCompensationType: string
{
    /** Paid in the payroll run. The statutory default absent a written pacto. */
    case Payment = 'payment';

    /** Accrued as rest-day balance (Código del Trabajo art. 32 §4), see OvertimeRestDayBalance. */
    case RestDays = 'rest_days';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.overtime.compensation_types.'.$this->value);
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
