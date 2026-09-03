<?php

namespace App\Enums;

/**
 * The outcome of evaluating one ImportRow against an ImportSchema (KOL-94.3).
 */
enum ImportRowStatus: string
{
    case Ready = 'ready';
    case Warning = 'warning';
    case Error = 'error';
    case Skipped = 'skipped';
}
