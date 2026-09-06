<?php

namespace App\Actions\Imports;

use App\Models\ImportRun;
use App\Services\Imports\ImportSchema;
use App\Support\Imports\ImportFieldLabels;
use App\Support\Imports\ImportIssue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Writes ProcessImportRun's commit-pass error-report CSV (KOL-94.8, KOL-103):
 * UTF-8 with a BOM, comma-delimited, one line per ImportIssue with columns
 * Fila/Columna/Severidad/Mensaje. {@see self::open()} truncates and rewrites
 * the file from scratch — a retried job attempt re-derives issues for rows a
 * previous attempt already committed (see ProcessImportRun::commit()) rather
 * than appending to whatever a prior attempt left behind.
 */
final class ImportErrorReportWriter
{
    /** @var resource */
    private $handle;

    /** @var array<string, string> */
    private array $labels = [];

    public function open(ImportRun $importRun, ImportSchema $schema): void
    {
        $path = self::diskPath($importRun);

        Storage::disk('local')->makeDirectory(dirname($path));

        $importRun->update(['error_report_path' => $path]);

        $handle = fopen(Storage::disk('local')->path($path), 'w');

        if ($handle === false) {
            throw new RuntimeException("Unable to open error-report file for writing: {$path}");
        }

        $this->handle = $handle;

        fwrite($this->handle, "\xEF\xBB\xBF");
        fputcsv($this->handle, ['Fila', 'Columna', 'Severidad', 'Mensaje']);

        $this->labels = ImportFieldLabels::build($schema);
    }

    /**
     * @param  list<ImportIssue>  $issues
     */
    public function write(int $rowNumber, array $issues): void
    {
        foreach ($issues as $issue) {
            fputcsv($this->handle, [
                $rowNumber,
                $issue->field !== null ? ($this->labels[$issue->field] ?? $issue->field) : '',
                $issue->severity->label(),
                $issue->message,
            ]);
        }
    }

    public function close(): void
    {
        fclose($this->handle);
    }

    public static function diskPath(ImportRun $importRun): string
    {
        return "import-runs/{$importRun->organization_id}/{$importRun->id}-errores.csv";
    }
}
