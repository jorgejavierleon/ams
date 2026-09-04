<?php

use App\Enums\ColumnMappingStatus;
use App\Enums\ImportRunStatus;
use App\Enums\ImportStrategy;
use App\Models\ImportRun;
use App\Models\Organization;
use App\Models\User;
use App\Services\Imports\EmployeeImportSchema;
use App\Support\Imports\ImportField;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function importAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * @param  list<string>  $header
 */
function csvUploadFixture(array $header, int $rowCount, string $clientName = 'empleados.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, $header);

    for ($i = 0; $i < $rowCount; $i++) {
        fputcsv($handle, array_map(fn (string $column): string => "{$column}-{$i}", $header));
    }

    fclose($handle);

    return new UploadedFile($path, $clientName, 'text/csv', null, true);
}

/**
 * A real .xlsx-named file that isn't actually a spreadsheet (AC #6's
 * "renamed-extension" case) — random bytes fail Xlsx::canRead()'s real
 * ZIP/OOXML check, and the .xlsx extension keeps Csv's extension-trust
 * shortcut from ever firing.
 */
function garbageXlsxUploadFixture(string $clientName = 'empleados.xlsx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    // A PNG signature keeps mime detection deterministically binary, unlike
    // plain random_bytes() which can occasionally look like valid text.
    file_put_contents($path, "\x89PNG\r\n\x1a\n".random_bytes(256));

    return new UploadedFile($path, $clientName, 'application/octet-stream', null, true);
}

test('a valid upload creates an ImportRun scoped to the organization and reaches MappingReview', function () {
    Storage::fake('local');
    $organization = Organization::factory()->create();
    $admin = importAdmin($organization);

    $header = ['Nombre', 'Apellido paterno', 'RUT', 'Email'];
    $file = csvUploadFixture($header, 3);

    $response = $this->actingAs($admin)->post(route('imports.employee.store'), ['file' => $file]);

    $importRun = ImportRun::sole();
    $response->assertRedirect(route('imports.show', $importRun));

    expect($importRun->organization_id)->toBe($organization->id)
        ->and($importRun->user_id)->toBe($admin->id)
        ->and($importRun->status)->toBe(ImportRunStatus::MappingReview)
        ->and($importRun->expires_at)->not->toBeNull()
        ->and($importRun->original_filename)->toBe('empleados.csv')
        ->and($importRun->column_mapping)->toHaveCount(4)
        ->and($importRun->column_mapping[0])->toMatchArray([
            'sourceColumnIndex' => 0,
            'sourceHeaderLabel' => 'Nombre',
            'targetField' => 'first_name',
            'status' => ColumnMappingStatus::Mapped->value,
        ]);

    Storage::disk('local')->assertExists($importRun->disk_path);
});

test('auto-mapping a fixture header set produces the expected Mapped/Unmapped split', function () {
    Storage::fake('local');
    $admin = importAdmin();

    // 'Nombre'..'Zona horaria' are exact schema-field-label matches (score
    // 1.0). 'Apellido paterno' is deliberately ambiguous (0.5 token-overlap
    // against both last_name's and second_last_name's labels — below the
    // 0.6 threshold). 'xyz' matches nothing.
    $header = ['Nombre', 'Apellido paterno', 'RUT', 'Email', 'Zona horaria', 'xyz'];
    $file = csvUploadFixture($header, 2);

    $this->actingAs($admin)->post(route('imports.employee.store'), ['file' => $file]);

    $importRun = ImportRun::sole();

    expect($importRun->column_mapping)->toHaveCount(6)
        ->and($importRun->column_mapping[0])->toMatchArray(['targetField' => 'first_name', 'status' => 'mapped'])
        ->and($importRun->column_mapping[1])->toMatchArray(['targetField' => null, 'status' => 'unmapped'])
        ->and($importRun->column_mapping[2])->toMatchArray(['targetField' => 'rut', 'status' => 'mapped'])
        ->and($importRun->column_mapping[3])->toMatchArray(['targetField' => 'email', 'status' => 'mapped'])
        ->and($importRun->column_mapping[4])->toMatchArray(['targetField' => 'timezone', 'status' => 'mapped'])
        ->and($importRun->column_mapping[5])->toMatchArray(['targetField' => null, 'status' => 'unmapped']);
});

/**
 * @param  array<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}>  $overrides  keyed by sourceColumnIndex, merged over the run's stored skeleton
 */
function mappingRunFor(User $admin, array $overrides = []): ImportRun
{
    $importRun = ImportRun::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'status' => ImportRunStatus::MappingReview,
        'column_mapping' => [
            ['sourceColumnIndex' => 0, 'sourceHeaderLabel' => 'Nombre', 'targetField' => null, 'status' => 'unmapped'],
            ['sourceColumnIndex' => 1, 'sourceHeaderLabel' => 'Apellido', 'targetField' => null, 'status' => 'unmapped'],
            ['sourceColumnIndex' => 2, 'sourceHeaderLabel' => 'RUT', 'targetField' => null, 'status' => 'unmapped'],
            ['sourceColumnIndex' => 3, 'sourceHeaderLabel' => 'Email', 'targetField' => null, 'status' => 'unmapped'],
            ['sourceColumnIndex' => 4, 'sourceHeaderLabel' => 'Zona horaria', 'targetField' => null, 'status' => 'unmapped'],
            ['sourceColumnIndex' => 5, 'sourceHeaderLabel' => 'Notas', 'targetField' => null, 'status' => 'unmapped'],
        ],
    ]);

    return $importRun;
}

test('saving a mapping with all required fields mapped succeeds', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);

    $mapping = [
        ['sourceColumnIndex' => 0, 'sourceHeaderLabel' => 'Nombre', 'targetField' => 'first_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 1, 'sourceHeaderLabel' => 'Apellido', 'targetField' => 'last_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 2, 'sourceHeaderLabel' => 'RUT', 'targetField' => 'rut', 'status' => 'mapped'],
        ['sourceColumnIndex' => 3, 'sourceHeaderLabel' => 'Email', 'targetField' => 'email', 'status' => 'mapped'],
        ['sourceColumnIndex' => 4, 'sourceHeaderLabel' => 'Zona horaria', 'targetField' => 'timezone', 'status' => 'mapped'],
        ['sourceColumnIndex' => 5, 'sourceHeaderLabel' => 'Notas', 'targetField' => null, 'status' => 'ignored'],
    ];

    $this->actingAs($admin)
        ->patch(route('imports.mapping.update', $importRun), ['mapping' => $mapping])
        ->assertRedirect();

    expect($importRun->fresh()->column_mapping)->toEqual($mapping);
});

test('saving a mapping with a required field still Unmapped is rejected', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);
    $original = $importRun->column_mapping;

    $mapping = [
        ['sourceColumnIndex' => 0, 'sourceHeaderLabel' => 'Nombre', 'targetField' => 'first_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 1, 'sourceHeaderLabel' => 'Apellido', 'targetField' => 'last_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 2, 'sourceHeaderLabel' => 'RUT', 'targetField' => 'rut', 'status' => 'mapped'],
        ['sourceColumnIndex' => 3, 'sourceHeaderLabel' => 'Email', 'targetField' => 'email', 'status' => 'mapped'],
        // timezone left unmapped
        ['sourceColumnIndex' => 4, 'sourceHeaderLabel' => 'Zona horaria', 'targetField' => null, 'status' => 'unmapped'],
        ['sourceColumnIndex' => 5, 'sourceHeaderLabel' => 'Notas', 'targetField' => null, 'status' => 'ignored'],
    ];

    $this->actingAs($admin)
        ->patch(route('imports.mapping.update', $importRun), ['mapping' => $mapping])
        ->assertSessionHasErrors('mapping');

    expect($importRun->fresh()->column_mapping)->toEqual($original);
});

test('saving a mapping with two columns mapped to the same field is rejected', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);

    $mapping = [
        ['sourceColumnIndex' => 0, 'sourceHeaderLabel' => 'Nombre', 'targetField' => 'first_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 1, 'sourceHeaderLabel' => 'Apellido', 'targetField' => 'first_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 2, 'sourceHeaderLabel' => 'RUT', 'targetField' => 'rut', 'status' => 'mapped'],
        ['sourceColumnIndex' => 3, 'sourceHeaderLabel' => 'Email', 'targetField' => 'email', 'status' => 'mapped'],
        ['sourceColumnIndex' => 4, 'sourceHeaderLabel' => 'Zona horaria', 'targetField' => 'timezone', 'status' => 'mapped'],
        ['sourceColumnIndex' => 5, 'sourceHeaderLabel' => 'Notas', 'targetField' => null, 'status' => 'ignored'],
    ];

    $this->actingAs($admin)
        ->patch(route('imports.mapping.update', $importRun), ['mapping' => $mapping])
        ->assertSessionHasErrors('mapping');
});

test('updating the mapping on an import run outside MappingReview/PreviewReady is refused', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);
    $importRun->update(['status' => ImportRunStatus::Processing]);

    $this->actingAs($admin)
        ->patch(route('imports.mapping.update', $importRun), ['mapping' => $importRun->column_mapping])
        ->assertStatus(409);
});

test('saving CreateOnly without a match key succeeds', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);

    $this->actingAs($admin)
        ->patch(route('imports.strategy.update', $importRun), ['strategy' => 'create_only'])
        ->assertRedirect();

    $importRun->refresh();
    expect($importRun->strategy)->toBe(ImportStrategy::CreateOnly)
        ->and($importRun->match_key)->toBeNull()
        ->and($importRun->status)->toBe(ImportRunStatus::MappingReview);
});

test('saving UpdateOnly without a match key is rejected', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);

    $this->actingAs($admin)
        ->patch(route('imports.strategy.update', $importRun), ['strategy' => 'update_only'])
        ->assertSessionHasErrors('match_key');

    expect($importRun->fresh()->strategy)->toBeNull();
});

test('saving strategy and match key persists correctly and keeps the run at MappingReview', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);

    $this->actingAs($admin)
        ->patch(route('imports.strategy.update', $importRun), [
            'strategy' => 'create_and_update',
            'match_key' => 'email',
        ])
        ->assertRedirect();

    $importRun->refresh();
    expect($importRun->strategy)->toBe(ImportStrategy::CreateAndUpdate)
        ->and($importRun->match_key)->toBe('email')
        ->and($importRun->status)->toBe(ImportRunStatus::MappingReview);
});

test('a match key submitted with CreateOnly is dropped rather than persisted', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);

    $this->actingAs($admin)
        ->patch(route('imports.strategy.update', $importRun), [
            'strategy' => 'create_only',
            'match_key' => 'rut',
        ])
        ->assertRedirect();

    expect($importRun->fresh()->match_key)->toBeNull();
});

test('an unsupported match key is rejected', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);

    $this->actingAs($admin)
        ->patch(route('imports.strategy.update', $importRun), [
            'strategy' => 'update_only',
            'match_key' => 'supervisor',
        ])
        ->assertSessionHasErrors('match_key');
});

test('updating the strategy on an import run outside MappingReview/PreviewReady is refused', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);
    $importRun->update(['status' => ImportRunStatus::Processing]);

    $this->actingAs($admin)
        ->patch(route('imports.strategy.update', $importRun), ['strategy' => 'create_only'])
        ->assertStatus(409);
});

test('a second user in the same organization cannot update another user\'s strategy', function () {
    $organization = Organization::factory()->create();
    $owner = importAdmin($organization);
    $otherUser = importAdmin($organization);

    $importRun = mappingRunFor($owner);

    $this->actingAs($otherUser)
        ->patch(route('imports.strategy.update', $importRun), ['strategy' => 'create_only'])
        ->assertNotFound();
});

test('an over-threshold file is rejected without creating an ImportRun', function () {
    config(['imports.sync_preview_threshold.csv' => 2]);
    $admin = importAdmin();

    $file = csvUploadFixture(['Nombre'], 3);

    $this->actingAs($admin)
        ->post(route('imports.employee.store'), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(ImportRun::count())->toBe(0);
});

test('a renamed-extension file is rejected', function () {
    $admin = importAdmin();

    $this->actingAs($admin)
        ->post(route('imports.employee.store'), ['file' => garbageXlsxUploadFixture()])
        ->assertSessionHasErrors('file');

    expect(ImportRun::count())->toBe(0);
});

test('a user without Import:Employee is forbidden from the upload step', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('imports.employee.create'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('imports.employee.store'), ['file' => csvUploadFixture(['Nombre'], 1)])
        ->assertForbidden();

    expect(ImportRun::count())->toBe(0);
});

test('a user outside the ImportRun organization cannot view it', function () {
    $owner = importAdmin();
    $importRun = ImportRun::factory()->create([
        'organization_id' => $owner->organization_id,
        'user_id' => $owner->id,
        'status' => ImportRunStatus::MappingReview,
    ]);

    $outsider = importAdmin();

    $this->actingAs($outsider)
        ->get(route('imports.show', $importRun))
        ->assertNotFound();
});

test('a second user in the same organization cannot view or update another user\'s ImportRun', function () {
    $organization = Organization::factory()->create();
    $owner = importAdmin($organization);
    $otherUser = importAdmin($organization);

    $importRun = mappingRunFor($owner);

    $this->actingAs($otherUser)
        ->get(route('imports.show', $importRun))
        ->assertNotFound();

    $this->actingAs($otherUser)
        ->patch(route('imports.mapping.update', $importRun), ['mapping' => $importRun->column_mapping])
        ->assertNotFound();
});

test('the template download has the expected header row and order', function () {
    $admin = importAdmin();

    $response = $this->actingAs($admin)
        ->get(route('imports.employee.template', ['format' => 'excel']))
        ->assertOk();

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, TestResponse::fromBaseResponse($response->baseResponse)->streamedContent());

    try {
        $spreadsheet = (new XlsxReader)->load($path);
    } finally {
        unlink($path);
    }

    $sheet = $spreadsheet->getActiveSheet();
    $headerRow = $sheet->toArray()[0];

    $expectedLabels = collect((new EmployeeImportSchema)->fields())
        ->reject(fn (ImportField $field): bool => $field->isIdentifierOnly)
        ->map(fn (ImportField $field): string => $field->label)
        ->values()
        ->all();

    expect($headerRow)->toBe($expectedLabels)
        ->and($sheet->getHighestDataRow())->toBe(1);
});

test('an unsupported template format 404s', function () {
    $admin = importAdmin();

    $this->actingAs($admin)
        ->get(route('imports.employee.template', ['format' => 'pdf']))
        ->assertNotFound();
});

/**
 * @param  list<string>  $header
 * @param  list<list<string>>  $rows
 * @param  array<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}>  $mapping
 */
function previewRunFor(
    User $admin,
    array $header,
    array $rows,
    array $mapping,
    ?ImportStrategy $strategy = null,
    ?string $matchKey = null,
): ImportRun {
    $importRun = ImportRun::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $admin->id,
        'status' => ImportRunStatus::MappingReview,
        'column_mapping' => $mapping,
        'strategy' => $strategy,
        'match_key' => $matchKey,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, $header);

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    $diskPath = "import-runs/{$importRun->organization_id}/{$importRun->id}.csv";
    Storage::disk('local')->put($diskPath, file_get_contents($path));
    unlink($path);

    $importRun->update(['disk_path' => $diskPath]);

    return $importRun;
}

/**
 * @return array<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}>
 */
function fullyMappedCoreFields(): array
{
    return [
        ['sourceColumnIndex' => 0, 'sourceHeaderLabel' => 'Nombre', 'targetField' => 'first_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 1, 'sourceHeaderLabel' => 'Apellido', 'targetField' => 'last_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 2, 'sourceHeaderLabel' => 'RUT', 'targetField' => 'rut', 'status' => 'mapped'],
        ['sourceColumnIndex' => 3, 'sourceHeaderLabel' => 'Email', 'targetField' => 'email', 'status' => 'mapped'],
        ['sourceColumnIndex' => 4, 'sourceHeaderLabel' => 'Zona horaria', 'targetField' => 'timezone', 'status' => 'mapped'],
    ];
}

test('previewing a clean fixture yields all-Ready counts', function () {
    Storage::fake('local');
    $admin = importAdmin();

    $header = ['Nombre', 'Apellido', 'RUT', 'Email', 'Zona horaria'];
    $rows = [
        ['Juan', 'Perez', validRut(11111111), 'juan@example.com', 'America/Santiago'],
        ['Maria', 'Lopez', validRut(22222222), 'maria@example.com', 'America/Santiago'],
    ];

    $importRun = previewRunFor($admin, $header, $rows, fullyMappedCoreFields(), ImportStrategy::CreateOnly);

    $this->actingAs($admin)
        ->post(route('imports.preview.store', $importRun))
        ->assertRedirect();

    $importRun->refresh();
    expect($importRun->status)->toBe(ImportRunStatus::PreviewReady)
        ->and($importRun->preview_counts)->toEqual(['ready' => 2, 'warning' => 0, 'error' => 0, 'skipped' => 0]);
});

test('previewing a fixture with an unresolved reference, a required-field gap, and a duplicate match-key yields the expected Error breakdown', function () {
    Storage::fake('local');
    $admin = importAdmin();

    $existingRut = validRut(99999999);
    User::factory()->create(['organization_id' => $admin->organization_id, 'rut' => $existingRut]);

    $header = ['Nombre', 'Apellido', 'RUT', 'Email', 'Zona horaria', 'Centro de costo'];
    $mapping = [
        ...fullyMappedCoreFields(),
        ['sourceColumnIndex' => 5, 'sourceHeaderLabel' => 'Centro de costo', 'targetField' => 'cost_center', 'status' => 'mapped'],
    ];

    $rows = [
        // Clean row.
        ['Juan', 'Perez', validRut(11111111), 'juan@example.com', 'America/Santiago', ''],
        // Unresolved reference: no CostCenter named "No existe".
        ['Ana', 'Diaz', validRut(22222222), 'ana@example.com', 'America/Santiago', 'No existe'],
        // Required-field gap: rut left blank.
        ['Pedro', 'Soto', '', 'pedro@example.com', 'America/Santiago', ''],
        // Duplicate match-key: rut already belongs to an existing employee, CreateOnly can't reuse it.
        ['Otro', 'Empleado', $existingRut, 'otro@example.com', 'America/Santiago', ''],
    ];

    $importRun = previewRunFor($admin, $header, $rows, $mapping, ImportStrategy::CreateOnly);

    $this->actingAs($admin)
        ->post(route('imports.preview.store', $importRun))
        ->assertRedirect();

    $importRun->refresh();
    expect($importRun->status)->toBe(ImportRunStatus::PreviewReady)
        ->and($importRun->preview_counts)->toEqual(['ready' => 1, 'warning' => 0, 'error' => 3, 'skipped' => 0]);
});

test('running preview outside MappingReview is refused', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);
    $importRun->update(['status' => ImportRunStatus::Processing]);

    $this->actingAs($admin)
        ->post(route('imports.preview.store', $importRun))
        ->assertStatus(409);
});

test('running preview with a required field still unmapped is rejected', function () {
    Storage::fake('local');
    $admin = importAdmin();

    $mapping = [
        ...array_slice(fullyMappedCoreFields(), 0, 4),
        // timezone left unmapped.
        ['sourceColumnIndex' => 4, 'sourceHeaderLabel' => 'Zona horaria', 'targetField' => null, 'status' => 'unmapped'],
    ];

    $importRun = previewRunFor($admin, ['Nombre'], [], $mapping, ImportStrategy::CreateOnly);

    $this->actingAs($admin)
        ->post(route('imports.preview.store', $importRun))
        ->assertSessionHasErrors('preview');

    expect($importRun->fresh()->status)->toBe(ImportRunStatus::MappingReview);
});

test('running preview without a strategy set is rejected', function () {
    Storage::fake('local');
    $admin = importAdmin();

    $importRun = previewRunFor($admin, ['Nombre'], [], fullyMappedCoreFields(), null);

    $this->actingAs($admin)
        ->post(route('imports.preview.store', $importRun))
        ->assertSessionHasErrors('preview');
});

test('running preview when the strategy needs a match key but none is set is rejected', function () {
    Storage::fake('local');
    $admin = importAdmin();

    $importRun = previewRunFor($admin, ['Nombre'], [], fullyMappedCoreFields(), ImportStrategy::UpdateOnly, null);

    $this->actingAs($admin)
        ->post(route('imports.preview.store', $importRun))
        ->assertSessionHasErrors('preview');
});

test('resubmitting mapping while PreviewReady demotes the run and clears preview_counts', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);
    $importRun->update([
        'status' => ImportRunStatus::PreviewReady,
        'preview_counts' => ['ready' => 1, 'warning' => 0, 'error' => 0, 'skipped' => 0],
    ]);

    $mapping = [
        ...fullyMappedCoreFields(),
        ['sourceColumnIndex' => 5, 'sourceHeaderLabel' => 'Notas', 'targetField' => null, 'status' => 'ignored'],
    ];

    $this->actingAs($admin)
        ->patch(route('imports.mapping.update', $importRun), ['mapping' => $mapping])
        ->assertRedirect();

    $importRun->refresh();
    expect($importRun->status)->toBe(ImportRunStatus::MappingReview)
        ->and($importRun->preview_counts)->toBeNull();
});

test('resubmitting strategy while PreviewReady demotes the run and clears preview_counts', function () {
    $admin = importAdmin();
    $importRun = mappingRunFor($admin);
    $importRun->update([
        'status' => ImportRunStatus::PreviewReady,
        'preview_counts' => ['ready' => 1, 'warning' => 0, 'error' => 0, 'skipped' => 0],
    ]);

    $this->actingAs($admin)
        ->patch(route('imports.strategy.update', $importRun), ['strategy' => 'create_only'])
        ->assertRedirect();

    $importRun->refresh();
    expect($importRun->status)->toBe(ImportRunStatus::MappingReview)
        ->and($importRun->preview_counts)->toBeNull()
        ->and($importRun->strategy)->toBe(ImportStrategy::CreateOnly);
});
