<?php

namespace App\Actions\Imports;

use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

/**
 * Reads an ImportRun's uploaded file back off the local disk and returns its
 * data rows, header stripped — shared by {@see PreviewImportRun}'s sync
 * evaluation pass and {@see ProcessImportRun}'s commit pass (KOL-102) so the
 * two never parse the file differently.
 */
final class ReadImportFileRows
{
    /**
     * @return list<list<mixed>>
     */
    public function handle(ImportRun $importRun): array
    {
        $path = Storage::disk('local')->path($importRun->disk_path);
        $extension = pathinfo((string) $importRun->disk_path, PATHINFO_EXTENSION);

        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;

        if ($reader instanceof CsvReader) {
            $reader->setInputEncoding(CsvReader::GUESS_ENCODING);
        }

        $reader->setReadDataOnly(true);

        $rows = $reader->load($path)->getActiveSheet()->toArray(null, true, true, false);

        return array_values(array_map(
            fn (array $row): array => array_values($row),
            array_slice($rows, 1),
        ));
    }
}
