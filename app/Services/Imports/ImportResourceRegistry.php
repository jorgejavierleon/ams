<?php

namespace App\Services\Imports;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The single map from a resource-type URL key to everything the bulk-import
 * wizard needs for it (KOL-107 AC #4). Adding a resource beyond Employee
 * (KOL-108's Shift Assignments) means adding one entry here and an
 * ImportSchema/ImportTemplate implementation — no changes to
 * ImportWizardController, CreateImportRunFromUpload, or ProcessImportRun.
 *
 * The key itself doubles as two conventions ImportWizardController relies
 * on directly, the same way every other Inertia page/lang pair in this app
 * already does by bare string, so a new entry's key must match both: the
 * Inertia page directory `resources/js/pages/imports/<key>/`, and the
 * top-level `lang/{locale}/ui.php` key (`ui.<key>.import.*`).
 */
final class ImportResourceRegistry
{
    /**
     * @return array<string, ImportResourceDefinition>
     */
    private static function definitions(): array
    {
        return [
            'employees' => new ImportResourceDefinition(
                schema: EmployeeImportSchema::class,
                permission: 'Import:Employee',
                template: EmployeeImportTemplate::class,
                indexRoute: 'employees.index',
            ),
        ];
    }

    public static function find(string $resourceType): ?ImportResourceDefinition
    {
        return self::definitions()[$resourceType] ?? null;
    }

    public static function findOrFail(string $resourceType): ImportResourceDefinition
    {
        return self::find($resourceType) ?? throw new NotFoundHttpException("Unregistered import resource type: {$resourceType}");
    }
}
