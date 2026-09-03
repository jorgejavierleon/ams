<?php

namespace App\Support\Imports;

use App\Actions\Imports\EvaluateImportRow;
use App\Enums\ImportRowStatus;
use App\Models\ImportRun;

/**
 * One data row from the uploaded file, evaluated against an ImportSchema by
 * {@see EvaluateImportRow} (KOL-94.3). Ephemeral —
 * never persisted as an individual database row; only aggregate counts land
 * on {@see ImportRun}.
 */
final readonly class ImportRow
{
    /**
     * @param  array<string, mixed>  $resolvedData
     * @param  list<ImportIssue>  $issues
     */
    public function __construct(
        public int $rowNumber,
        public array $resolvedData,
        public ImportRowStatus $status,
        public array $issues,
        public ?int $matchedModelId,
    ) {}
}
