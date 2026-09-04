<?php

namespace App\Actions\Imports;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Services\Imports\ImportSchema;

/**
 * Runs every uploaded data row through {@see EvaluateImportRow} synchronously
 * (KOL-94.5, KOL-101) and persists the aggregate outcome on the ImportRun:
 * MappingReview -> PreviewReady. Files are guaranteed sync-sized by the
 * upload-time threshold (KOL-98), so no chunking/queueing happens here —
 * that belongs to ProcessImportRun's commit pass (KOL-102).
 */
class PreviewImportRun
{
    public function __construct(
        private EvaluateImportRow $evaluateImportRow,
        private ReadImportFileRows $readImportFileRows,
        private BuildColumnMappings $buildColumnMappings,
    ) {}

    public function handle(ImportRun $importRun, ImportSchema $schema): void
    {
        $columnMappings = $this->buildColumnMappings->handle($importRun);

        $counts = ['ready' => 0, 'warning' => 0, 'error' => 0, 'skipped' => 0];

        foreach ($this->readImportFileRows->handle($importRun) as $index => $rawRow) {
            $result = $this->evaluateImportRow->handle(
                $schema,
                $columnMappings,
                $rawRow,
                $index + 2, // +1 for the header row, +1 to make it 1-based
                $importRun->strategy,
                $importRun->match_key,
            );

            $counts[$result->status->value]++;
        }

        $importRun->update([
            'preview_counts' => $counts,
            'status' => ImportRunStatus::PreviewReady,
        ]);
    }
}
