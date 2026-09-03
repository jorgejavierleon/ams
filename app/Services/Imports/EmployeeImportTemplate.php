<?php

namespace App\Services\Imports;

use App\Services\Reports\EmployeeMasterExporter;
use App\Services\Reports\ReportWriter;
use App\Support\Imports\ImportField;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates the downloadable Employee import template (KOL-94.8): a
 * headers-only file — no example data row, per KOL-94.8 decision #4 — whose
 * columns are exactly {@see EmployeeImportSchema}'s field order, excluding
 * identifier-only fields (`id`) which exist for match-key lookup, not for
 * a user to fill in.
 *
 * Mirrors {@see EmployeeMasterExporter} exactly: same FORMATS/ReportWriter
 * path, so the template needs no new file-generation code, per KOL-94.8
 * decision #1.
 */
class EmployeeImportTemplate
{
    /**
     * @var list<string>
     */
    public const FORMATS = ['excel', 'csv'];

    public function __construct(private ReportWriter $writer, private EmployeeImportSchema $schema) {}

    public function download(string $format): Response
    {
        ['fragment' => $fragment, 'filename' => $filename] = $this->prepare();

        return match ($format) {
            'excel' => $this->writer->excel($this->document($fragment), $filename),
            'csv' => $this->writer->csv($fragment, $filename, ';'),
            default => throw new InvalidArgumentException("Unsupported template format: {$format}"),
        };
    }

    /**
     * @return array{fragment: string, filename: string}
     */
    private function prepare(): array
    {
        $previousLocale = App::getLocale();
        App::setLocale('es');

        try {
            $labels = collect($this->schema->fields())
                ->reject(fn (ImportField $field): bool => $field->isIdentifierOnly)
                ->map(fn (ImportField $field): string => $field->label)
                ->all();

            $fragment = View::make('exports.employees.import-template', [
                'labels' => $labels,
            ])->render();

            return ['fragment' => $fragment, 'filename' => Str::slug(__('ui.employees.import.template.title'))];
        } finally {
            App::setLocale($previousLocale);
        }
    }

    private function document(string $fragment): string
    {
        return View::make('exports.employees.document', [
            'title' => __('ui.employees.import.template.title'),
            'content' => $fragment,
        ])->render();
    }
}
