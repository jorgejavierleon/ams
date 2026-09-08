<?php

namespace App\Services\Imports;

use App\Actions\Imports\EvaluateImportRow;
use App\Enums\ImportStrategy;
use App\Models\ImportRun;
use App\Support\Imports\ImportField;
use App\Support\Imports\ReferenceResolution;
use Illuminate\Database\Eloquent\Model;

/**
 * The per-resource configuration describing how to import a given resource
 * (KOL-94.3): its importable columns, validation rules, reference-field
 * resolution, match-key lookup, and target model. One implementation exists
 * per importable resource — {@see EmployeeImportSchema} is the first.
 *
 * The universal per-row evaluation sequence that composes these methods
 * lives in {@see EvaluateImportRow}, not here, so every
 * resource enforces the blank-cell/reference-error policy identically.
 */
interface ImportSchema
{
    /**
     * @return array<int, ImportField>
     */
    public function fields(): array;

    /**
     * Laravel-native validation rules, operating on the cast+resolved row
     * (post reference-resolution, so FK-exists checks run against ids, not
     * labels). `$existingMatch` excludes the row's own record from
     * uniqueness checks on update.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(ImportStrategy $strategy, ?Model $existingMatch): array;

    /**
     * Substitute every reference field's human-readable label for its
     * resolved value (e.g. a cost centre name for its id). The resolution
     * mechanism is entirely internal to the concrete schema.
     *
     * @param  array<string, mixed>  $row
     */
    public function resolveReferences(array $row): ReferenceResolution;

    /**
     * Look up an existing record by a match key eligible field's normalized
     * value.
     */
    public function findExisting(string $matchKey, mixed $normalizedValue): ?Model;

    /**
     * @return class-string<Model>
     */
    public function targetModel(): string;

    /**
     * A blank instance for a row with no existing match, pre-populated with
     * whatever the schema deliberately never collects as a mapped field
     * (tenant stamps, a random password, and the like) — never called for a
     * row that already resolved an existing match via {@see findExisting()}.
     */
    public function newModel(ImportRun $importRun): Model;

    /**
     * Runs once the row's resolved data has been filled onto the model but
     * before it's saved — for a derived attribute the schema's own
     * {@see fields()} doesn't carry directly (e.g. a `name` computed from
     * first/last name).
     */
    public function beforeSave(Model $model): void;

    /**
     * Runs once the model has been saved — for a side effect that must
     * happen exactly once per created/updated row, outside the row's own
     * mapped data (e.g. assigning a default role on create).
     */
    public function afterSave(Model $model, bool $wasCreated): void;
}
