<?php

namespace App\Enums;

/**
 * Drives generic cell-value casting for an ImportField (KOL-94.3). Needed
 * because KOL-94.1's research found CSV dates aren't reliably typed and
 * booleans arrive as varied tokens, so casting can't just trust the reader.
 */
enum ImportFieldType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Date = 'date';
    case Boolean = 'boolean';
}
