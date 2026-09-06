<?php

namespace App\Enums;

/**
 * Severity of a single ImportIssue (KOL-94.3): a Warning still allows the
 * row to import, an Error excludes it.
 */
enum ImportIssueSeverity: string
{
    case Warning = 'warning';
    case Error = 'error';

    /**
     * The Spanish label shown in both the CSV error report (KOL-103) and the
     * preview step's on-screen issue table (KOL-111) — the report is always
     * in Spanish regardless of the acting locale, so this isn't translated.
     */
    public function label(): string
    {
        return match ($this) {
            self::Warning => 'Advertencia',
            self::Error => 'Error',
        };
    }
}
