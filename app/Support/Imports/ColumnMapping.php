<?php

namespace App\Support\Imports;

use App\Enums\ColumnMappingStatus;
use App\Models\ImportRun;
use App\Models\ReportExport;

/**
 * The pairing of one column in the uploaded file to one ImportSchema field
 * (KOL-94.3). Persisted as a JSON-cast array on {@see ImportRun},
 * mirroring {@see ReportExport::$filters} — nothing here needs
 * independent identity or querying.
 */
final readonly class ColumnMapping
{
    public function __construct(
        public int $sourceColumnIndex,
        public ?string $sourceHeaderLabel,
        public ?string $targetField,
        public ColumnMappingStatus $status,
    ) {}
}
