<?php

namespace App\Exceptions;

use App\Support\Duration;
use RuntimeException;

/**
 * A rest-day balance consumption was asked to draw more than an employee
 * actually has available (KOL-47 AC #2).
 */
class RestDayBalanceRefused extends RuntimeException
{
    public static function insufficientBalance(Duration $requested, Duration $available): self
    {
        return new self(
            "Cannot consume {$requested->toTimeString()} of rest-day balance: only {$available->toTimeString()} is available."
        );
    }
}
