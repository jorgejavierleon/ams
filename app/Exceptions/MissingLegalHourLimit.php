<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * No legal working-hour limit version covers the requested date.
 *
 * Thrown rather than falling back to the nearest version: a calculation that
 * silently borrowed a rule from a date it does not apply to would produce a
 * figure nobody could defend, and the whole point of the versioned limits is
 * that the applicable rule is never guessed.
 */
class MissingLegalHourLimit extends RuntimeException
{
    public static function for(CarbonInterface $date): self
    {
        return new self(
            "No legal working-hour limit is defined for {$date->toDateString()}."
        );
    }
}
