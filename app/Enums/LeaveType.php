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
     * Pastel background hex used to render this type on the leaves calendar
     * and its legend. Drawn from the same violet/blue/amber/rose/teal hue
     * family as the dashboard's icon tones (see dashboard.tsx iconTones), so
     * the calendar matches the rest of the app's palette rather than
     * inventing its own. Unlike the dashboard's Tailwind classes, this is a
     * single fixed hex used in both light and dark mode — the same approach
     * already taken for the attendance chart's colors (see app.css's
     * --attendance-* tokens) — rather than a per-theme pair.
     */
    public function color(): string
    {
        return match ($this) {
            self::Vacation => '#ccfbf1', // teal-100
            self::Medical => '#ffe4e6', // rose-100
            self::Unpaid => '#fef3c7', // amber-100
            self::Paid => '#dbeafe', // blue-100
            self::Other => '#ede9fe', // violet-100
        };
    }

    /**
     * Readable foreground hex paired with {@see color()}'s pastel background,
     * from the same hue at a darker shade.
     */
    public function textColor(): string
    {
        return match ($this) {
            self::Vacation => '#0f766e', // teal-700
            self::Medical => '#be123c', // rose-700
            self::Unpaid => '#b45309', // amber-700
            self::Paid => '#1d4ed8', // blue-700
            self::Other => '#6d28d9', // violet-700
        };
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
     * All types as value/label/color/textColor quadruples for the calendar legend.
     *
     * @return array<int, array{value: string, label: string, color: string, textColor: string}>
     */
    public static function legendOptions(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'color' => $type->color(),
                'textColor' => $type->textColor(),
            ],
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
