<?php

namespace App\Actions\Imports;

use App\Enums\ColumnMappingStatus;
use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Services\Imports\ImportSchema;
use App\Support\Imports\ColumnMapping;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

/**
 * Runs every uploaded data row through {@see EvaluateImportRow} synchronously
 * (KOL-94.5, KOL-101) and persists the aggregate outcome on the ImportRun:
 * MappingReview -> PreviewReady. Files are guaranteed sync-sized by the
 * upload-time threshold (KOL-98), so no chunking/queueing happens here —
 * that belongs to ProcessImportRun's commit pass (KOL-102).
 */
class PreviewImportRun
{
    public function __construct(private EvaluateImportRow $evaluateImportRow) {}

    public function handle(ImportRun $importRun, ImportSchema $schema): void
    {
        $columnMappings = $this->columnMappings($importRun);

        $counts = ['ready' => 0, 'warning' => 0, 'error' => 0, 'skipped' => 0];

        foreach ($this->readDataRows($importRun) as $index => $rawRow) {
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

    /**
     * @return list<ColumnMapping>
     */
    private function columnMappings(ImportRun $importRun): array
    {
        return collect($importRun->column_mapping ?? [])
            ->map(fn (array $row): ColumnMapping => new ColumnMapping(
                $row['sourceColumnIndex'],
                $row['sourceHeaderLabel'],
                $row['targetField'],
                ColumnMappingStatus::from($row['status']),
            ))
            ->all();
    }

    /**
     * @return list<list<mixed>>
     */
    private function readDataRows(ImportRun $importRun): array
    {
        $path = Storage::disk('local')->path($importRun->disk_path);
        $extension = pathinfo((string) $importRun->disk_path, PATHINFO_EXTENSION);

        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;

        if ($reader instanceof CsvReader) {
            $reader->setInputEncoding(CsvReader::GUESS_ENCODING);
        }

        $reader->setReadDataOnly(true);

        $rows = $reader->load($path)->getActiveSheet()->toArray(null, true, true, false);

        return array_slice($rows, 1);
    }
}
