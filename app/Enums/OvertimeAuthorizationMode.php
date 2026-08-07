<?php

namespace App\Enums;

/**
 * How an organization authorises overtime (PRD §7.1).
 *
 * `PreAuthorization` is the Talana-style flow: the employee requests the hours
 * before working them and a supervisor approves, so the calculation only ever
 * runs against hours somebody already agreed to. `PostHoc` is the Buk-style
 * safety net: marks generate a shift excess that a supervisor approves or
 * objects afterwards. `Combined` runs both — planned overtime through requests,
 * unrequested excess still caught by the post-hoc queue.
 *
 * Consumers should ask {@see self::allowsRequests()} or
 * {@see self::allowsShiftExcess()} rather than comparing cases, so that
 * `Combined` never has to be special-cased at every call site.
 */
enum OvertimeAuthorizationMode: string
{
    case PreAuthorization = 'pre_authorization';
    case PostHoc = 'post_hoc';
    case Combined = 'combined';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.organization_settings.overtime_authorization_modes.'.$this->value);
    }

    /**
     * Whether employees may request overtime ahead of working it (Mode A).
     */
    public function allowsRequests(): bool
    {
        return $this !== self::PostHoc;
    }

    /**
     * Whether marks exceeding the shift generate a reviewable excess (Mode B).
     */
    public function allowsShiftExcess(): bool
    {
        return $this !== self::PreAuthorization;
    }

    /**
     * All modes as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $mode): array => ['value' => $mode->value, 'label' => $mode->label()],
            self::cases(),
        );
    }
}
