<?php

namespace App\Actions\Imports;

use App\Enums\ColumnMappingStatus;
use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Throwable;

/**
 * Turns an uploaded file into a MappingReview-ready ImportRun (KOL-98,
 * KOL-94.5's `store()` contract): validates the file's *real* format via
 * {@see IOFactory} (never trusting the extension, per KOL-94.1), enforces
 * the sync-preview row threshold before anything is persisted (KOL-94.1
 * AC #4 — an over-threshold file must never create a run), then stores the
 * file and parses its header row into an initial, fully-Unmapped
 * ColumnMapping — the real auto-mapping guess is KOL-99's ColumnAutoMapper,
 * not this ticket's concern.
 */
class CreateImportRunFromUpload
{
    /**
     * @throws ValidationException
     */
    public function handle(UploadedFile $file): ImportRun
    {
        $path = $file->getRealPath();
        $reader = $this->resolveReader($path);
        $format = $reader instanceof CsvReader ? 'csv' : 'excel';
        $threshold = (int) config("imports.sync_preview_threshold.{$format}");

        if ($reader instanceof CsvReader) {
            $reader->setInputEncoding(CsvReader::GUESS_ENCODING);

            // A cheap upper-bound line count rejects a huge CSV before
            // PhpSpreadsheet builds a Cell object per value — every logical
            // CSV row spans at least one physical line, so a line count at
            // or under the threshold guarantees the real row count is too.
            if ($this->csvLineCountExceeds($path, $threshold)) {
                $this->rejectTooManyRows($threshold);
            }
        }

        // Style parsing (dates, number formats) isn't needed to read the
        // header row's labels, and skipping it is cheaper for a large file —
        // ProcessImportRun (KOL-102) will re-load without this flag once it
        // needs to interpret data cells per KOL-94.1's date-handling caveat.
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $header = $rows[0] ?? [];
        $rowCount = count($rows) - 1;

        if ($rowCount > $threshold) {
            $this->rejectTooManyRows($threshold);
        }

        $importRun = ImportRun::create([
            'status' => ImportRunStatus::Pending,
            'expires_at' => now()->addHours((int) config('imports.expiry_hours')),
        ]);

        $extension = $format === 'excel' ? 'xlsx' : 'csv';
        $diskPath = "import-runs/{$importRun->organization_id}/{$importRun->id}.{$extension}";

        try {
            Storage::disk('local')->putFileAs(
                "import-runs/{$importRun->organization_id}",
                $file,
                "{$importRun->id}.{$extension}",
            );
        } catch (Throwable $exception) {
            // Never leave a Pending run with no file behind it — nothing
            // prunes it yet (KOL-94.4's pruning command doesn't exist until
            // a later ticket).
            $importRun->delete();

            throw $exception;
        }

        $importRun->update([
            'status' => ImportRunStatus::MappingReview,
            'disk_path' => $diskPath,
            'original_filename' => $file->getClientOriginalName(),
            'column_mapping' => $this->columnMappingSkeleton($header),
        ]);

        return $importRun;
    }

    /**
     * @throws ValidationException
     */
    private function resolveReader(string $path): CsvReader|XlsxReader
    {
        try {
            /** @var CsvReader|XlsxReader $reader */
            $reader = IOFactory::createReaderForFile($path, [IOFactory::READER_XLSX, IOFactory::READER_CSV]);

            return $reader;
        } catch (SpreadsheetReaderException) {
            throw ValidationException::withMessages([
                'file' => __('ui.employees.import.errors.unsupported_format'),
            ]);
        }
    }

    private function csvLineCountExceeds(string $path, int $threshold): bool
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return false;
        }

        $lines = 0;

        while (fgets($handle) !== false) {
            $lines++;

            // +1 for the header row.
            if ($lines > $threshold + 1) {
                fclose($handle);

                return true;
            }
        }

        fclose($handle);

        return false;
    }

    /**
     * @throws ValidationException
     */
    private function rejectTooManyRows(int $threshold): never
    {
        throw ValidationException::withMessages([
            'file' => __('ui.employees.import.errors.too_many_rows', ['max' => $threshold]),
        ]);
    }

    /**
     * @param  array<int, mixed>  $header
     * @return array<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}>
     */
    private function columnMappingSkeleton(array $header): array
    {
        return collect($header)
            ->values()
            ->map(fn (mixed $label, int $index): array => [
                'sourceColumnIndex' => $index,
                'sourceHeaderLabel' => $label === null ? null : (string) $label,
                'targetField' => null,
                'status' => ColumnMappingStatus::Unmapped->value,
            ])
            ->all();
    }
}
