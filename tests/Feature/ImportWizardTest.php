<?php

use App\Enums\ColumnMappingStatus;
use App\Enums\ImportRunStatus;
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
        ->and($importRun->status)->toBe(ImportRunStatus::MappingReview)
        ->and($importRun->expires_at)->not->toBeNull()
        ->and($importRun->original_filename)->toBe('empleados.csv')
        ->and($importRun->column_mapping)->toHaveCount(4)
        ->and($importRun->column_mapping[0])->toMatchArray([
            'sourceColumnIndex' => 0,
            'sourceHeaderLabel' => 'Nombre',
            'targetField' => null,
            'status' => ColumnMappingStatus::Unmapped->value,
        ]);

    Storage::disk('local')->assertExists($importRun->disk_path);
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
        'status' => ImportRunStatus::MappingReview,
    ]);

    $outsider = importAdmin();

    $this->actingAs($outsider)
        ->get(route('imports.show', $importRun))
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
