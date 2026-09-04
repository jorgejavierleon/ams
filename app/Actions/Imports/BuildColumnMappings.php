<?php

namespace App\Actions\Imports;

use App\Enums\ColumnMappingStatus;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use App\Support\Imports\ColumnMapping;

/**
 * Turns an ImportRun's persisted `column_mapping` JSON rows into
 * {@see ColumnMapping} value objects — shared by {@see PreviewImportRun}'s
 * sync evaluation pass and {@see ProcessImportRun}'s commit pass (KOL-102) so
 * the two never interpret a run's mapping differently.
 */
final class BuildColumnMappings
{
    /**
     * @return list<ColumnMapping>
     */
    public function handle(ImportRun $importRun): array
    {
        return array_values(array_map(
            fn (array $row): ColumnMapping => new ColumnMapping(
                $row['sourceColumnIndex'],
                $row['sourceHeaderLabel'],
                $row['targetField'],
                ColumnMappingStatus::from($row['status']),
            ),
            $importRun->column_mapping ?? [],
        ));
    }
}
