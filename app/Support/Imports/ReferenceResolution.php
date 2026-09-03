<?php

namespace App\Support\Imports;

use App\Services\Imports\ImportSchema;

/**
 * The result of {@see ImportSchema::resolveReferences()}
 * (KOL-94.3): the row with every reference label substituted for its
 * resolved value, and the list of reference fields that could not be
 * resolved. A non-empty `unresolvedFields` makes the whole ImportRow an
 * Error — never a per-field Warning.
 */
final readonly class ReferenceResolution
{
    /**
     * @param  array<string, mixed>  $resolved
     * @param  list<string>  $unresolvedFields
     */
    public function __construct(
        public array $resolved,
        public array $unresolvedFields,
    ) {}
}
