<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case PendingSignature = 'pending_signature';
    case Signed = 'signed';
    case Rejected = 'rejected';
    case Archived = 'archived';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.documents.statuses.'.$this->value);
    }

    /**
     * The shadcn `Badge` variant used to colour the status pill.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Published, self::Signed => 'default',
            self::Rejected => 'destructive',
            self::Archived => 'outline',
            self::Draft, self::PendingSignature => 'secondary',
        };
    }

    /**
     * A shared, semantic tone for the status so the tinted status chips across
     * the document views resolve their colours from a single place.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Published, self::Signed => 'success',
            self::PendingSignature => 'warning',
            self::Rejected => 'destructive',
            self::Draft, self::Archived => 'neutral',
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
