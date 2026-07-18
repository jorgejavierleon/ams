<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case PendingSignature = 'pending_signature';
    case Signed = 'signed';
    case Rejected = 'rejected';
    case Voided = 'voided';
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
            self::Rejected, self::Voided => 'destructive',
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
            self::Rejected, self::Voided => 'destructive',
            self::Draft, self::Archived => 'neutral',
        };
    }

    /**
     * Whether an admin may void (withdraw) a document in this status. A document
     * is voidable only while it is "live" — published or out for signature.
     * Draft documents are edited or deleted instead, and Signed / Rejected /
     * already-Voided documents are terminal.
     */
    public function canBeVoided(): bool
    {
        return in_array($this, [self::Published, self::PendingSignature], true);
    }

    /**
     * Whether an admin may duplicate a document in this status into a fresh
     * draft to re-issue a corrected version. Offered for the terminal states
     * an admin would want to correct: voided, rejected, or signed.
     */
    public function canBeDuplicated(): bool
    {
        return in_array($this, [self::Voided, self::Rejected, self::Signed], true);
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
