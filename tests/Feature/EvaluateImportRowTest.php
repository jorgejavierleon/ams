<?php

use App\Actions\Imports\EvaluateImportRow;
use App\Enums\ColumnMappingStatus;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStrategy;
use App\Models\Organization;
use App\Models\User;
use App\Services\Imports\EmployeeImportSchema;
use App\Support\Imports\ColumnMapping;
use App\Support\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Builds ColumnMapping/rawRow pairs from a flat field => raw value map, in
 * insertion order, mirroring how a wizard-confirmed mapping pairs one
 * uploaded column per field.
 *
 * @param  array<string, mixed>  $fields
 * @return array{0: list<ColumnMapping>, 1: list<mixed>}
 */
function mappedImportRow(array $fields): array
{
    $mappings = [];
    $rawRow = [];
    $index = 0;

    foreach ($fields as $field => $value) {
        $mappings[] = new ColumnMapping($index, $field, $field, ColumnMappingStatus::Mapped);
        $rawRow[] = $value;
        $index++;
    }

    return [$mappings, $rawRow];
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->admin = User::factory()->create(['organization_id' => $this->organization->id]);
    $this->actingAs($this->admin);
    $this->schema = new EmployeeImportSchema;
});

test('a clean create produces a Ready row with resolved data', function () {
    [$mappings, $rawRow] = mappedImportRow([
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'email' => 'ana@example.com',
        'rut' => validRut(11111111),
        'timezone' => 'America/Santiago',
        'phone' => '+56911111111',
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 1, ImportStrategy::CreateOnly, null,
    );

    expect($result->status)->toBe(ImportRowStatus::Ready)
        ->and($result->issues)->toBe([])
        ->and($result->matchedModelId)->toBeNull()
        ->and($result->resolvedData['first_name'])->toBe('Ana')
        ->and($result->resolvedData['email'])->toBe('ana@example.com')
        ->and($result->resolvedData['rut'])->toBe(Rut::normalize(validRut(11111111)))
        ->and($result->resolvedData['phone'])->toBe('+56911111111');
});

test('an update with a blank non-match-key cell makes no change to that field', function () {
    $existing = User::factory()->create([
        'organization_id' => $this->organization->id,
        'rut' => Rut::normalize(validRut(22222222)),
        'phone' => '+56922222222',
    ]);

    [$mappings, $rawRow] = mappedImportRow([
        'rut' => validRut(22222222),
        'phone' => '',
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 2, ImportStrategy::UpdateOnly, 'rut',
    );

    expect($result->status)->toBe(ImportRowStatus::Ready)
        ->and($result->matchedModelId)->toBe($existing->id)
        ->and($result->resolvedData)->not->toHaveKey('phone');
});

test('an unresolved reference makes the whole row an Error, never a field Warning', function () {
    [$mappings, $rawRow] = mappedImportRow([
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'email' => 'ana2@example.com',
        'rut' => validRut(33333333),
        'timezone' => 'America/Santiago',
        'cost_center' => 'Does Not Exist',
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 3, ImportStrategy::CreateOnly, null,
    );

    expect($result->status)->toBe(ImportRowStatus::Error)
        ->and($result->resolvedData)->toBe([])
        ->and($result->issues)->toHaveCount(1)
        ->and($result->issues[0]->field)->toBe('cost_center')
        ->and($result->issues[0]->severity->value)->toBe('error');
});

test('the RUT match key normalizes before comparing', function () {
    $existing = User::factory()->create([
        'organization_id' => $this->organization->id,
        'rut' => Rut::normalize(validRut(44444444)),
    ]);

    [$mappings, $rawRow] = mappedImportRow([
        'rut' => Rut::format(validRut(44444444)),
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 4, ImportStrategy::UpdateOnly, 'rut',
    );

    expect($result->matchedModelId)->toBe($existing->id);
});

test('the Email match key is case-insensitive', function () {
    $existing = User::factory()->create([
        'organization_id' => $this->organization->id,
        'email' => 'match@example.com',
    ]);

    [$mappings, $rawRow] = mappedImportRow([
        'email' => 'MATCH@EXAMPLE.COM',
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 5, ImportStrategy::UpdateOnly, 'email',
    );

    expect($result->matchedModelId)->toBe($existing->id);
});

test('the ID match key is an exact integer match', function () {
    $existing = User::factory()->create(['organization_id' => $this->organization->id]);

    [$mappings, $rawRow] = mappedImportRow([
        'id' => (string) $existing->id,
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 6, ImportStrategy::UpdateOnly, 'id',
    );

    expect($result->matchedModelId)->toBe($existing->id);
});

test('the ID match key has no effect under CreateOnly', function () {
    $existing = User::factory()->create(['organization_id' => $this->organization->id]);

    [$mappings, $rawRow] = mappedImportRow([
        'id' => (string) $existing->id,
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'email' => 'ana3@example.com',
        'rut' => validRut(55555555),
        'timezone' => 'America/Santiago',
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 7, ImportStrategy::CreateOnly, 'id',
    );

    expect($result->status)->toBe(ImportRowStatus::Ready)
        ->and($result->matchedModelId)->toBeNull();
});

test('a mapped id column never leaks into resolvedData, even when it is not the active match key', function () {
    $existing = User::factory()->create([
        'organization_id' => $this->organization->id,
        'rut' => Rut::normalize(validRut(77777777)),
    ]);
    $other = User::factory()->create(['organization_id' => $this->organization->id]);

    [$mappings, $rawRow] = mappedImportRow([
        'rut' => validRut(77777777),
        'id' => (string) $other->id,
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 9, ImportStrategy::UpdateOnly, 'rut',
    );

    expect($result->status)->toBe(ImportRowStatus::Ready)
        ->and($result->matchedModelId)->toBe($existing->id)
        ->and($result->resolvedData)->not->toHaveKey('id');
});

test('a supervisor reference resolving to the row itself is rejected', function () {
    $existing = User::factory()->create([
        'organization_id' => $this->organization->id,
        'rut' => Rut::normalize(validRut(88888888)),
    ]);

    [$mappings, $rawRow] = mappedImportRow([
        'rut' => validRut(88888888),
        'supervisor' => validRut(88888888),
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 10, ImportStrategy::UpdateOnly, 'rut',
    );

    expect($result->status)->toBe(ImportRowStatus::Error)
        ->and(collect($result->issues)->pluck('field')->all())->toContain('supervisor_id');
});

test('UpdateOnly skips a row with no matching existing record', function () {
    [$mappings, $rawRow] = mappedImportRow([
        'rut' => validRut(99999999),
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 11, ImportStrategy::UpdateOnly, 'rut',
    );

    expect($result->status)->toBe(ImportRowStatus::Skipped)
        ->and($result->matchedModelId)->toBeNull()
        ->and($result->resolvedData)->toBe([]);
});

test('CreateOnly rejects a row missing a required field', function () {
    [$mappings, $rawRow] = mappedImportRow([
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'rut' => validRut(66666666),
        'timezone' => 'America/Santiago',
    ]);

    $result = app(EvaluateImportRow::class)->handle(
        $this->schema, $mappings, $rawRow, 8, ImportStrategy::CreateOnly, null,
    );

    expect($result->status)->toBe(ImportRowStatus::Error)
        ->and(collect($result->issues)->pluck('field')->all())->toContain('email');
});
