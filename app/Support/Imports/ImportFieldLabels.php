<?php

namespace App\Support\Imports;

use App\Services\Imports\ImportSchema;
use Illuminate\Support\Facades\App;

/**
 * Maps an ImportSchema's field names to their Spanish labels (KOL-94.8),
 * shared by the CSV error report (ImportErrorReportWriter, KOL-103) and the
 * preview step's on-screen issue table (KOL-111) so the two never drift.
 * Always Spanish regardless of the acting locale — there is none in a queue
 * worker anyway, and the report/table are user-facing artifacts of the same
 * Spanish-only wizard.
 */
final class ImportFieldLabels
{
    /**
     * A reference field's issue is keyed by its resolved `{name}_id` column
     * (EvaluateImportRow's validator runs post-resolution), so each
     * isReference field's label is aliased under both its own name and that
     * suffixed key.
     *
     * @return array<string, string>
     */
    public static function build(ImportSchema $schema): array
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
