<?php

namespace App\Support\Imports;

use App\Enums\ImportIssueSeverity;

/**
 * A problem found on a single ImportRow (KOL-94.3). `field` is null for
 * whole-row issues, such as an unresolved reference.
 */
final readonly class ImportIssue
{
    public function __construct(
        public ?string $field,
        public string $message,
        public ImportIssueSeverity $severity,
    ) {}
}
