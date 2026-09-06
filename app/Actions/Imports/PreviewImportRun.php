<?php

namespace App\Actions\Imports;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\ImportRunIssue;
use App\Services\Imports\ImportSchema;
use Illuminate\Support\Carbon;

/**
 * Runs every uploaded data row through {@see EvaluateImportRow} synchronously
 * (KOL-94.5, KOL-101) and persists the aggregate outcome on the ImportRun:
 * MappingReview -> PreviewReady. Also persists each row's individual issues
 * (KOL-111) so the preview step's on-screen table can show exactly what's
 * wrong without a commit or CSV download; a run's issues are replaced, never
 * appended to, on every re-preview. Files are guaranteed sync-sized by the
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
        $issueRows = [];
        $now = Carbon::now();

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

            foreach ($result->issues as $issue) {
                $issueRows[] = [
                    'import_run_id' => $importRun->id,
                    'row_number' => $result->rowNumber,
                    'field' => $issue->field,
                    'severity' => $issue->severity->value,
                    'message' => $issue->message,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $importRun->issues()->delete();

        if ($issueRows !== []) {
            ImportRunIssue::insert($issueRows);
        }

        $importRun->update([
            'preview_counts' => $counts,
            'status' => ImportRunStatus::PreviewReady,
        ]);
    }
}
