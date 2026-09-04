<?php

namespace App\Actions\Imports;

use App\Enums\ImportIssueSeverity;
use App\Models\ImportRun;
use App\Services\Imports\EmployeeImportTemplate;
use App\Services\Imports\ImportSchema;
use App\Support\Imports\ImportField;
use App\Support\Imports\ImportIssue;
use Illuminate\Support\Facades\App;
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

        $this->labels = $this->buildLabels($schema);
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
                $issue->severity === ImportIssueSeverity::Error ? 'Error' : 'Advertencia',
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

    /**
     * The report is always in Spanish regardless of the acting locale (there
     * is none in a queue worker anyway), mirroring
     * {@see EmployeeImportTemplate}'s same defensive
     * App::setLocale('es'). A reference field's issue is keyed by its
     * resolved `{name}_id` column (EvaluateImportRow's validator runs
     * post-resolution), so each isReference field's label is aliased under
     * both its own name and that suffixed key.
     *
     * @return array<string, string>
     */
    private function buildLabels(ImportSchema $schema): array
    {
        $previousLocale = App::getLocale();
        App::setLocale('es');

        try {
            $labels = [];

            foreach ($schema->fields() as $field) {
                /** @var ImportField $field */
                $labels[$field->name] = $field->label;

                if ($field->isReference) {
                    $labels[$field->name.'_id'] = $field->label;
                }
            }

            return $labels;
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
