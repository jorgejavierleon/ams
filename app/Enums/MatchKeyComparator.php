<?php

namespace App\Enums;

use App\Support\Rut;
use Illuminate\Support\Str;

/**
 * How a match-key cell value is normalized before comparison (KOL-94.2): RUT
 * normalized via {@see Rut::normalize()}, email lowercased, ID an exact
 * integer match. A closed enum over the comparison itself (rather than a
 * bare closure) so it stays a plain ImportField property that serializes
 * cleanly across queue boundaries, matching {@see ContractType}'s convention.
 */
enum MatchKeyComparator: string
{
    case Exact = 'exact';
    case CaseInsensitive = 'case_insensitive';
    case NormalizedRut = 'normalized_rut';

    public function normalize(mixed $value): string|int
    {
        return match ($this) {
            self::Exact => is_numeric($value) ? (int) $value : (string) $value,
            self::CaseInsensitive => Str::lower(trim((string) $value)),
            self::NormalizedRut => Rut::normalize(trim((string) $value)),
        };
    }
}
