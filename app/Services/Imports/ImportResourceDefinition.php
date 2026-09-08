<?php

namespace App\Services\Imports;

/**
 * One {@see ImportResourceRegistry} entry: everything ImportWizardController,
 * CreateImportRunFromUpload, and ProcessImportRun need to handle a given
 * resource type without ever type-hinting its concrete classes (KOL-107).
 */
final readonly class ImportResourceDefinition
{
    /**
     * @param  class-string<ImportSchema>  $schema
     * @param  class-string<ImportTemplate>  $template
     * @param  string  $indexRoute  the named route ImportWizardController::destroy() sends the user back to after cancelling a run (e.g. the resource's own list page)
     */
    public function __construct(
        public string $schema,
        public string $permission,
        public string $template,
        public string $indexRoute,
    ) {}

    public function schema(): ImportSchema
    {
        return app($this->schema);
    }

    public function template(): ImportTemplate
    {
        return app($this->template);
    }
}
