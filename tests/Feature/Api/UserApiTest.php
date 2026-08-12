<?php

use App\Models\Organization;
use App\Models\Position;
use App\Models\Premise;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('unauthenticated user requests return 401', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});

test('an authenticated employee receives their identity and effective permissions', function () {
    $employee = User::factory()->employee()->create([
        'organization_id' => Organization::factory()->create()->id,
        'first_name' => 'Empleado',
        'last_name' => 'Demo',
        'rut' => '21437581-8',
    ]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/user')->assertOk();

    $response
        ->assertJsonPath('id', $employee->id)
        ->assertJsonPath('name', $employee->name)
        ->assertJsonPath('first_name', 'Empleado')
        ->assertJsonPath('last_name', 'Demo')
        ->assertJsonPath('rut', '21437581-8')
        ->assertJsonPath('email', $employee->email)
        ->assertJsonPath('avatar', null)
        // The factory's `employee()` state assigns neither: an employee with
        // no position or premise configured reads as null, not an error.
        ->assertJsonPath('position', null)
        ->assertJsonPath('premise', null);

    // The eleven self-service permissions the `employee` role carries.
    expect($response->json('permissions'))
        ->toBeArray()
        ->toHaveCount(11)
        ->each->toBeString();

    expect($response->json('permissions'))->toEqualCanonicalizing([
        'RequestOwn:Leave',
        'ViewOwn:Leave',
        'CancelOwn:Leave',
        'ClockOwn:Mark',
        'ViewOwn:Mark',
        'ViewOwn:Workday',
        'ReviewOwn:MarkModification',
        'ViewOwn:Document',
        'SignOwn:Document',
        'RequestOwn:OvertimeAuthorization',
        'ViewOwn:OvertimeAuthorization',
    ]);
});

test('the payload exposes only the agreed fields and no sensitive columns', function () {
    Sanctum::actingAs(User::factory()->employee()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]));

    $response = $this->getJson('/api/v1/user')->assertOk();

    expect(array_keys($response->json()))->toEqualCanonicalizing([
        'id', 'name', 'first_name', 'last_name', 'rut', 'email', 'personal_email', 'phone', 'avatar',
        'position', 'premise', 'supervisor', 'contract_start_date', 'permissions',
    ]);

    $response
        ->assertJsonMissingPath('password')
        ->assertJsonMissingPath('two_factor_secret')
        ->assertJsonMissingPath('two_factor_recovery_codes')
        ->assertJsonMissingPath('remember_token')
        ->assertJsonMissingPath('organization_id')
        ->assertJsonMissingPath('is_admin')
        ->assertJsonMissingPath('vacation_days');
});

test('position and premise carry the related models\' names', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'position_id' => Position::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Operaria de Bodega',
        ])->id,
        'premise_id' => Premise::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Sucursal Ñuñoa',
        ])->id,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('position', 'Operaria de Bodega')
        ->assertJsonPath('premise', 'Sucursal Ñuñoa');
});

test('personal_email, phone, supervisor and contract_start_date carry their values', function () {
    $organization = Organization::factory()->create();
    $supervisor = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'name' => 'Supervisor Demo',
    ]);
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'personal_email' => 'empleado.personal@example.com',
        'phone' => '+56 9 1234 5678',
        'supervisor_id' => $supervisor->id,
        'contract_start_date' => '2024-03-01',
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('personal_email', 'empleado.personal@example.com')
        ->assertJsonPath('phone', '+56 9 1234 5678')
        ->assertJsonPath('supervisor', 'Supervisor Demo')
        ->assertJsonPath('contract_start_date', '2024-03-01');
});

test('personal_email, phone, supervisor and contract_start_date read as null rather than an error when unset', function () {
    $employee = User::factory()->employee()->create([
        'organization_id' => Organization::factory()->create()->id,
        'personal_email' => null,
        'phone' => null,
        'supervisor_id' => null,
        'contract_start_date' => null,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('personal_email', null)
        ->assertJsonPath('phone', null)
        ->assertJsonPath('supervisor', null)
        ->assertJsonPath('contract_start_date', null);
});

test('the request eager-loads position, premise and supervisor rather than lazy-loading each', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'position_id' => Position::factory()->create(['organization_id' => $organization->id])->id,
        'premise_id' => Premise::factory()->create(['organization_id' => $organization->id])->id,
        'supervisor_id' => User::factory()->employee()->create(['organization_id' => $organization->id])->id,
    ]);

    Sanctum::actingAs($employee);

    DB::enableQueryLog();

    $this->getJson('/api/v1/user')->assertOk();

    // Loose upper bound rather than an exact count, so it stays meaningful
    // through unrelated middleware/auth changes while still catching a
    // regression to per-relation lazy loads on a hot path. Raised from 6 to 7
    // for the third eager-loaded relation (supervisor).
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(7);

    DB::disableQueryLog();
});

test('a user with no roles and no permissions receives an empty permissions array', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/user')->assertOk();

    expect($response->json('permissions'))->toBe([]);

    // Encoded as [] rather than null or an object, which the client relies on.
    expect($response->getContent())->toContain('"permissions":[]');
});

test('permissions granted directly to the user are included alongside role permissions', function () {
    $employee = User::factory()->employee()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);
    $employee->givePermissionTo(Permission::create([
        'name' => 'ViewOwn:Payslip',
        'guard_name' => 'web',
    ]));

    Sanctum::actingAs($employee);

    $permissions = $this->getJson('/api/v1/user')->assertOk()->json('permissions');

    expect($permissions)->toHaveCount(12)
        ->toContain('ViewOwn:Payslip')
        ->toContain('ClockOwn:Mark');
});
