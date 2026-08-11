<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->assertJsonPath('avatar', null);

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
        'id', 'name', 'first_name', 'last_name', 'rut', 'email', 'avatar', 'permissions',
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
