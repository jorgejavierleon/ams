<?php

namespace App\Actions\Imports;

use App\Enums\ImportFieldType;
use App\Enums\ImportIssueSeverity;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStrategy;
use App\Services\Imports\ImportSchema;
use App\Support\Imports\ColumnMapping;
use App\Support\Imports\ImportField;
use App\Support\Imports\ImportIssue;
use App\Support\Imports\ImportRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The universal per-row evaluation sequence every ImportSchema shares
 * (KOL-94.3): map the raw file row through its ColumnMapping, cast scalars,
 * resolve references (an unresolved reference is always a whole-row Error,
 * never a per-field Warning), find an existing match, validate, omit blank
 * non-match-key cells (blank means no change, never clear-to-null), and
 * assemble the result. Kept in one place so this policy is enforced
 * identically for every resource rather than re-derived per schema.
 */
final class EvaluateImportRow
{
    /**
     * @param  list<ColumnMapping>  $columnMappings
     * @param  list<mixed>  $rawRow
     */
    public function handle(
        ImportSchema $schema,
        array $columnMappings,
        array $rawRow,
        int $rowNumber,
        ImportStrategy $strategy,
        ?string $matchKey,
    ): ImportRow {
        $fieldsByName = collect($schema->fields())->keyBy(fn (ImportField $field): string => $field->name);

        $mapped = $this->mapRow($columnMappings, $rawRow);
        $cast = $this->castRow($mapped, $fieldsByName);

        $resolution = $schema->resolveReferences($cast);

        if ($resolution->unresolvedFields !== []) {
            $issues = array_map(
                fn (string $field): ImportIssue => new ImportIssue(
                    $field,
                    "No matching {$field} found for \"{$cast[$field]}\".",
                    ImportIssueSeverity::Error,
                ),
                $resolution->unresolvedFields,
            );

            return new ImportRow($rowNumber, [], ImportRowStatus::Error, $issues, null);
        }

        $existingMatch = null;

        if ($matchKey !== null && $strategy->allowsMatching()) {
            $matchField = $fieldsByName->get($matchKey);
            $rawMatchValue = $resolution->resolved[$matchKey] ?? null;

            if ($matchField?->matchKeyComparator !== null && $rawMatchValue !== null) {
                $normalized = $matchField->matchKeyComparator->normalize($rawMatchValue);
                $existingMatch = $schema->findExisting($matchKey, $normalized);
            }
        }

        if ($strategy === ImportStrategy::UpdateOnly && $existingMatch === null) {
            $issues = [new ImportIssue($matchKey, 'No existing record found to update.', ImportIssueSeverity::Warning)];

            return new ImportRow($rowNumber, [], ImportRowStatus::Skipped, $issues, null);
        }

        $validator = Validator::make($resolution->resolved, $schema->rules($strategy, $existingMatch));

        if ($validator->fails()) {
            $issues = array_values(collect($validator->errors()->messages())
                ->flatMap(fn (array $messages, string $field): array => collect($messages)
                    ->map(fn (string $message): ImportIssue => new ImportIssue($field, $message, ImportIssueSeverity::Error))
                    ->all())
                ->all());

            return new ImportRow($rowNumber, [], ImportRowStatus::Error, $issues, $existingMatch?->getKey());
        }

        $resolvedData = $this->assembleResolvedData($resolution->resolved, $fieldsByName, $matchKey);

        return new ImportRow($rowNumber, $resolvedData, ImportRowStatus::Ready, [], $existingMatch?->getKey());
    }

    /**
     * @param  list<ColumnMapping>  $columnMappings
     * @param  list<mixed>  $rawRow
     * @return array<string, mixed>
     */
    private function mapRow(array $columnMappings, array $rawRow): array
    {
        $mapped = [];

        foreach ($columnMappings as $mapping) {
            if ($mapping->targetField === null) {
                continue;
            }

            $mapped[$mapping->targetField] = $rawRow[$mapping->sourceColumnIndex] ?? null;
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  Collection<string, ImportField>  $fieldsByName
     * @return array<string, mixed>
     */
    private function castRow(array $mapped, Collection $fieldsByName): array
    {
        $cast = [];

        foreach ($mapped as $fieldName => $value) {
            $field = $fieldsByName->get($fieldName);
            $cast[$fieldName] = $field === null ? $value : $this->castValue($value, $field->type);
        }

        return $cast;
    }

    private function castValue(mixed $value, ImportFieldType $type): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            ImportFieldType::String => (string) $value,
            ImportFieldType::Integer => is_numeric($value) ? (int) $value : $value,
            ImportFieldType::Decimal => is_numeric($value) ? (float) $value : $value,
            ImportFieldType::Date => $value,
            ImportFieldType::Boolean => $this->castBoolean($value),
        };
    }

    /**
     * Cell values arrive as varied tokens rather than a reliable bool
     * (KOL-94.1); an unrecognized token is left as-is so the `boolean`
     * validation rule flags it as an issue instead of silently coercing it.
     */
    private function castBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = Str::lower((string) $value);

        return match (true) {
            in_array($normalized, ['1', 'true', 'yes', 'si', 'sí', 'x'], true) => true,
            in_array($normalized, ['0', 'false', 'no'], true) => false,
            default => $value,
        };
    }

    /**
     * Drops identifier-only fields unconditionally (e.g. Employee's `id` —
     * it names the target record, never data to write) and blank
     * non-match-key cells (blank means no change, never clear-to-null).
     *
     * @param  array<string, mixed>  $resolved
     * @param  Collection<string, ImportField>  $fieldsByName
     * @return array<string, mixed>
     */
    private function assembleResolvedData(array $resolved, Collection $fieldsByName, ?string $matchKey): array
    {
        return array_filter(
            $resolved,
            fn (mixed $value, string $field): bool => ! ($fieldsByName->has($field) && $fieldsByName[$field]->isIdentifierOnly)
                && ($value !== null || $field === $matchKey),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
