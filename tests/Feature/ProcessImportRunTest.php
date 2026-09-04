<?php

use App\Enums\ImportRunStatus;
use App\Enums\ImportStrategy;
use App\Jobs\ProcessImportRun;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ImportRun;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ImportRunCompleted;
use App\Notifications\ImportRunFailed;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * @param  list<string>  $header
 * @param  list<list<string>>  $rows
 * @param  array<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}>  $mapping
 */
function commitRunFor(
    Organization $organization,
    User $requester,
    array $header,
    array $rows,
    array $mapping,
    ImportStrategy $strategy,
    ?string $matchKey = null,
): ImportRun {
    $importRun = ImportRun::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $requester->id,
        'status' => ImportRunStatus::PreviewReady,
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
function commitMapping(): array
{
    return [
        ['sourceColumnIndex' => 0, 'sourceHeaderLabel' => 'Nombre', 'targetField' => 'first_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 1, 'sourceHeaderLabel' => 'Apellido', 'targetField' => 'last_name', 'status' => 'mapped'],
        ['sourceColumnIndex' => 2, 'sourceHeaderLabel' => 'RUT', 'targetField' => 'rut', 'status' => 'mapped'],
        ['sourceColumnIndex' => 3, 'sourceHeaderLabel' => 'Email', 'targetField' => 'email', 'status' => 'mapped'],
        ['sourceColumnIndex' => 4, 'sourceHeaderLabel' => 'Zona horaria', 'targetField' => 'timezone', 'status' => 'mapped'],
    ];
}

test('committing a clean CreateOnly fixture creates the expected employees and notifies the requester, with no ambient auth or session', function () {
    Storage::fake('local');
    Notification::fake();

    $organization = Organization::factory()->create();
    Company::factory()->for($organization)->create();
    $requester = User::factory()->create(['organization_id' => $organization->id]);

    $header = ['Nombre', 'Apellido', 'RUT', 'Email', 'Zona horaria'];
    $rows = [
        ['Juan', 'Perez', validRut(11111111), 'juan@example.com', 'America/Santiago'],
        ['Maria', 'Lopez', validRut(22222222), 'maria@example.com', 'America/Santiago'],
    ];

    $importRun = commitRunFor($organization, $requester, $header, $rows, commitMapping(), ImportStrategy::CreateOnly);

    // No actingAs()/session() at all: proves the job resolves the tenant
    // from the run itself rather than ambient auth (CurrentOrganization),
    // which a real queued worker process would never have.
    app()->call([new ProcessImportRun($importRun->id), 'handle']);

    $importRun->refresh();

    expect($importRun->status)->toBe(ImportRunStatus::Completed)
        ->and($importRun->created_count)->toBe(2)
        ->and($importRun->updated_count)->toBe(0)
        ->and($importRun->skipped_count)->toBe(0)
        ->and($importRun->errored_count)->toBe(0)
        ->and($importRun->committed_through)->toBe(2);

    $employees = User::query()
        ->where('organization_id', $organization->id)
        ->whereIn('email', ['juan@example.com', 'maria@example.com'])
        ->get();

    expect($employees)->toHaveCount(2);

    foreach ($employees as $employee) {
        expect($employee->hasRole('employee'))->toBeTrue()
            ->and($employee->name)->toBe(trim("{$employee->first_name} {$employee->last_name}"))
            ->and($employee->password)->not->toBeNull()
            ->and($employee->company_id)->not->toBeNull();
    }

    Notification::assertSentTo($requester, ImportRunCompleted::class);
});

test('committing a CreateAndUpdate fixture updates a matched employee, leaving blank cells unchanged, scoped to the run\'s own organization', function () {
    Storage::fake('local');

    $organization = Organization::factory()->create();
    $requester = User::factory()->create(['organization_id' => $organization->id]);

    $existingRut = validRut(11111111);
    $existing = User::factory()->for($organization)->employee()->create([
        'rut' => $existingRut,
        'phone' => '+56911112222',
    ]);

    // A same-named cost centre in a different organization proves the job
    // resolves references against the run's own tenant, not globally.
    $otherOrganization = Organization::factory()->create();
    CostCenter::factory()->for($otherOrganization)->create(['name' => 'Ventas']);
    $costCenter = CostCenter::factory()->for($organization)->create(['name' => 'Ventas']);

    $header = ['Nombre', 'Apellido', 'RUT', 'Email', 'Zona horaria', 'Centro de costo'];
    $mapping = [
        ...commitMapping(),
        ['sourceColumnIndex' => 5, 'sourceHeaderLabel' => 'Centro de costo', 'targetField' => 'cost_center', 'status' => 'mapped'],
    ];

    // Blank last_name cell must not clear the existing value; RUT is the
    // match key so it isn't itself part of resolvedData.
    $rows = [
        [$existing->first_name, '', $existingRut, 'new-email@example.com', 'America/Santiago', 'Ventas'],
    ];

    $importRun = commitRunFor($organization, $requester, $header, $rows, $mapping, ImportStrategy::CreateAndUpdate, 'rut');

    app()->call([new ProcessImportRun($importRun->id), 'handle']);

    $importRun->refresh();
    $existing->refresh();

    expect($importRun->status)->toBe(ImportRunStatus::Completed)
        ->and($importRun->created_count)->toBe(0)
        ->and($importRun->updated_count)->toBe(1)
        ->and($existing->email)->toBe('new-email@example.com')
        ->and($existing->last_name)->not->toBeEmpty() // unchanged, not cleared
        ->and($existing->phone)->toBe('+56911112222') // untouched column, unchanged
        ->and($existing->cost_center_id)->toBe($costCenter->id);
});

test('a mid-file unique-constraint race is caught as that row\'s error, the chunk still commits, and the run still reaches Completed', function () {
    Storage::fake('local');

    $organization = Organization::factory()->create();
    $requester = User::factory()->create(['organization_id' => $organization->id]);

    $racingEmail = 'race@example.com';

    $header = ['Nombre', 'Apellido', 'RUT', 'Email', 'Zona horaria'];
    $rows = [
        ['Juan', 'Perez', validRut(11111111), 'juan@example.com', 'America/Santiago'],
        ['Ana', 'Diaz', validRut(22222222), $racingEmail, 'America/Santiago'],
        ['Pedro', 'Soto', validRut(33333333), 'pedro@example.com', 'America/Santiago'],
    ];

    $importRun = commitRunFor($organization, $requester, $header, $rows, commitMapping(), ImportStrategy::CreateOnly);

    // Simulate another process winning a race for the same email in the
    // narrow window between EvaluateImportRow's Rule::unique() check (which
    // passes — nothing exists yet) and this row's own INSERT. Inserted
    // without events so it can't recursively trigger this same listener.
    $listenerId = 'eloquent.saving: '.User::class;
    Event::listen($listenerId, function (User $user) use ($racingEmail, $organization, $listenerId): void {
        if ($user->email !== $racingEmail) {
            return;
        }

        Event::forget($listenerId);

        User::withoutEvents(fn () => User::factory()->for($organization)->create(['email' => $racingEmail]));
    });

    try {
        app()->call([new ProcessImportRun($importRun->id), 'handle']);
    } finally {
        Event::forget($listenerId);
    }

    $importRun->refresh();

    expect($importRun->status)->toBe(ImportRunStatus::Completed)
        ->and($importRun->created_count)->toBe(2)
        ->and($importRun->errored_count)->toBe(1)
        ->and($importRun->skipped_count)->toBe(0);

    expect(User::query()->where('email', 'juan@example.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'pedro@example.com')->exists())->toBeTrue();

    // The race is invisible to EvaluateImportRow (it only surfaces at
    // save()-time, inside applyRow) — the errored row still needs its own
    // report line, not just a bump to errored_count with nothing to explain
    // it.
    $csv = Storage::disk('local')->get($importRun->error_report_path);
    $lines = array_map('str_getcsv', explode("\n", rtrim(substr($csv, 3), "\n")));

    expect($lines)->toHaveCount(2) // header + the raced row's error
        ->and($lines[1][0])->toBe('3')
        ->and($lines[1][2])->toBe('Error');
});

test('a non-row-level failure is not swallowed, and failed() moves the run to Failed and notifies the requester', function () {
    Storage::fake('local');
    Notification::fake();

    $organization = Organization::factory()->create();
    $requester = User::factory()->create(['organization_id' => $organization->id]);

    $importRun = ImportRun::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $requester->id,
        'status' => ImportRunStatus::PreviewReady,
        'column_mapping' => commitMapping(),
        'strategy' => ImportStrategy::CreateOnly,
        // No file was ever stored at this path — a corrupt/missing upload.
        'disk_path' => "import-runs/{$organization->id}/missing.csv",
    ]);

    expect(fn () => app()->call([new ProcessImportRun($importRun->id), 'handle']))
        ->toThrow(Exception::class);

    expect(User::query()->where('organization_id', $organization->id)->where('id', '!=', $requester->id)->count())->toBe(0);

    (new ProcessImportRun($importRun->id))->failed(new RuntimeException('boom'));

    $importRun->refresh();
    expect($importRun->status)->toBe(ImportRunStatus::Failed);

    Notification::assertSentTo($requester, ImportRunFailed::class);
});

/**
 * @param  list<array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}>  $mapping
 */
function costCenterMapping(array $mapping): array
{
    return [
        ...$mapping,
        ['sourceColumnIndex' => 5, 'sourceHeaderLabel' => 'Centro de costo', 'targetField' => 'cost_center', 'status' => 'mapped'],
    ];
}

test('a mix of warnings and errors produces an error-report CSV with the right rows, columns, and values, in order', function () {
    Storage::fake('local');

    $organization = Organization::factory()->create();
    $requester = User::factory()->create(['organization_id' => $organization->id]);

    $header = ['Nombre', 'Apellido', 'RUT', 'Email', 'Zona horaria', 'Centro de costo'];
    $rows = [
        // No existing employee has this RUT -> UpdateOnly Warning (Skipped).
        ['Juan', 'Perez', validRut(11111111), 'juan@example.com', 'America/Santiago', ''],
        // No CostCenter named "Ventas" exists in this org -> unresolved
        // reference, a whole-row Error.
        ['Maria', 'Lopez', validRut(22222222), 'maria@example.com', 'America/Santiago', 'Ventas'],
    ];

    $importRun = commitRunFor($organization, $requester, $header, $rows, costCenterMapping(commitMapping()), ImportStrategy::UpdateOnly, 'rut');

    app()->call([new ProcessImportRun($importRun->id), 'handle']);

    $importRun->refresh();

    expect($importRun->status)->toBe(ImportRunStatus::Completed)
        ->and($importRun->skipped_count)->toBe(1)
        ->and($importRun->errored_count)->toBe(1)
        ->and($importRun->error_report_path)->toBe("import-runs/{$organization->id}/{$importRun->id}-errores.csv");

    $csv = Storage::disk('local')->get($importRun->error_report_path);

    expect($csv)->toStartWith("\xEF\xBB\xBF");

    $lines = array_map('str_getcsv', explode("\n", rtrim(substr($csv, 3), "\n")));

    expect($lines)->toHaveCount(3) // header + one issue per row
        ->and($lines[0])->toBe(['Fila', 'Columna', 'Severidad', 'Mensaje'])
        ->and($lines[1])->toBe(['2', 'RUT', 'Advertencia', 'No existing record found to update.'])
        ->and($lines[2])->toBe(['3', 'Centro de costo', 'Error', 'No matching cost_center found for "Ventas".']);
});

test('a retry re-derives the error report for rows an earlier attempt already committed', function () {
    Storage::fake('local');

    $organization = Organization::factory()->create();
    $requester = User::factory()->create(['organization_id' => $organization->id]);

    $header = ['Nombre', 'Apellido', 'RUT', 'Email', 'Zona horaria', 'Centro de costo'];
    $rows = [
        ['Juan', 'Perez', validRut(11111111), 'juan@example.com', 'America/Santiago', ''],
        ['Maria', 'Lopez', validRut(22222222), 'maria@example.com', 'America/Santiago', 'Ventas'],
    ];

    $importRun = commitRunFor($organization, $requester, $header, $rows, costCenterMapping(commitMapping()), ImportStrategy::UpdateOnly, 'rut');

    // Simulate a prior attempt that already committed row 1 (its Warning)
    // and died before reaching row 2 — a fresh attempt only re-runs row 2
    // through EvaluateImportRow below, so row 1's issue has to be
    // re-derived, not lost, to keep the file complete.
    $importRun->update(['committed_through' => 1, 'skipped_count' => 1]);

    app()->call([new ProcessImportRun($importRun->id), 'handle']);

    $importRun->refresh();

    expect($importRun->status)->toBe(ImportRunStatus::Completed)
        ->and($importRun->skipped_count)->toBe(1)
        ->and($importRun->errored_count)->toBe(1);

    $csv = Storage::disk('local')->get($importRun->error_report_path);
    $lines = array_map('str_getcsv', explode("\n", rtrim(substr($csv, 3), "\n")));

    expect($lines)->toHaveCount(3) // header + both rows' issues, despite row 1 not being re-applied
        ->and($lines[1])->toBe(['2', 'RUT', 'Advertencia', 'No existing record found to update.'])
        ->and($lines[2])->toBe(['3', 'Centro de costo', 'Error', 'No matching cost_center found for "Ventas".']);
});

test('a retried job resumes from committed_through without re-applying already-committed rows', function () {
    Storage::fake('local');

    $organization = Organization::factory()->create();
    $requester = User::factory()->create(['organization_id' => $organization->id]);

    $header = ['Nombre', 'Apellido', 'RUT', 'Email', 'Zona horaria'];
    $rows = [
        ['Juan', 'Perez', validRut(11111111), 'juan@example.com', 'America/Santiago'],
        ['Maria', 'Lopez', validRut(22222222), 'maria@example.com', 'America/Santiago'],
        ['Pedro', 'Soto', validRut(33333333), 'pedro@example.com', 'America/Santiago'],
    ];

    $importRun = commitRunFor($organization, $requester, $header, $rows, commitMapping(), ImportStrategy::CreateOnly);

    // Simulate a prior attempt that already committed row 1 before the job
    // died and got retried: the employee it wrote already exists, and the
    // run's cursor/counts already reflect that chunk.
    User::factory()->for($organization)->employee()->create([
        'email' => 'juan@example.com',
        'rut' => validRut(11111111),
    ]);
    $importRun->update(['committed_through' => 1, 'created_count' => 1]);

    app()->call([new ProcessImportRun($importRun->id), 'handle']);

    $importRun->refresh();

    expect($importRun->status)->toBe(ImportRunStatus::Completed)
        ->and($importRun->created_count)->toBe(3)
        ->and($importRun->committed_through)->toBe(3);

    expect(User::query()->where('email', 'juan@example.com')->count())->toBe(1)
        ->and(User::query()->whereIn('email', ['maria@example.com', 'pedro@example.com'])->count())->toBe(2);
});
