<?php

namespace App\Enums;

enum DocumentSignatureStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Rejected = 'rejected';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.documents.signatures.statuses.'.$this->value);
    }

    /**
     * The shadcn `Badge` variant used to colour the status pill.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Signed => 'default',
            self::Rejected => 'destructive',
            self::Pending => 'secondary',
        };
    }
}
