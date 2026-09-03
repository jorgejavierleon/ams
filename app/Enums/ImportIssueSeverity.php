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
}
