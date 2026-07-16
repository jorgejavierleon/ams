<?php

namespace App\Enums;

enum DocumentSignatureType: string
{
    case Employee = 'employee';
    case LegalRep = 'legal_rep';
    case Supervisor = 'supervisor';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.documents.signatures.types.'.$this->value);
    }
}
