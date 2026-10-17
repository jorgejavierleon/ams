<?php

use App\Models\Organization;
use App\Models\Premise;
use App\Models\TotemDevice;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function totemAdmin(?Organization $organization = null): User
{
    $admin = User::factory()->create([
        'organization_id' => ($organization ?? Organization::factory()->create())->id,
        'password' => 'secret-password',
    ]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function totemProvisionPayload(User $admin, Premise $premise, array $overrides = []): array
{
    return array_merge([
        'email' => $admin->email,
        'password' => 'secret-password',
        'premise_id' => $premise->id,
        'device_name' => 'Totem recepción',
    ], $overrides);
}

test('an admin provisions a totem device and receives a kiosk:punch token', function () {
    $admin = totemAdmin();
    $premise = Premise::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->postJson('/api/v1/totem-devices', totemProvisionPayload($admin, $premise))
        ->assertCreated()
        ->assertJsonStructure(['token', 'device' => ['id', 'name', 'premise' => ['id', 'name']]])
        ->assertJsonPath('device.premise.id', $premise->id);

    $device = TotemDevice::withoutGlobalScopes()->findOrFail($response->json('device.id'));
    expect($device->organization_id)->toBe($admin->organization_id)
        ->and($device->premise_id)->toBe($premise->id)
        ->and($device->tokens()->first()->abilities)->toBe(['kiosk:punch']);

    $this->app['auth']->forgetGuards();

    $this->withToken($response->json('token'))
        ->getJson('/api/v1/totem/device')
        ->assertOk()
        ->assertJsonPath('premise.id', $premise->id);
});

test('provisioning is rejected for a non-admin user', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'password' => 'secret-password',
    ]);
    $premise = Premise::factory()->create(['organization_id' => $organization->id]);

    $this->postJson('/api/v1/totem-devices', totemProvisionPayload($employee, $premise))
        ->assertForbidden();

    expect(TotemDevice::withoutGlobalScopes()->count())->toBe(0);
});

test('provisioning rejects wrong credentials', function () {
    $admin = totemAdmin();
    $premise = Premise::factory()->create(['organization_id' => $admin->organization_id]);

    $this->postJson('/api/v1/totem-devices', totemProvisionPayload($admin, $premise, ['password' => 'nope']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('provisioning rejects a premise from another organization', function () {
    $admin = totemAdmin();
    $foreignPremise = Premise::factory()->create();

    $this->postJson('/api/v1/totem-devices', totemProvisionPayload($admin, $foreignPremise))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('premise_id');

    expect(TotemDevice::withoutGlobalScopes()->count())->toBe(0);
});

test('several devices can be provisioned for the same and for different premises', function () {
    $admin = totemAdmin();
    $premiseA = Premise::factory()->create(['organization_id' => $admin->organization_id]);
    $premiseB = Premise::factory()->create(['organization_id' => $admin->organization_id]);

    foreach ([[$premiseA, 'Entrada'], [$premiseA, 'Salida'], [$premiseB, 'Bodega']] as [$premise, $name]) {
        $this->postJson('/api/v1/totem-devices', totemProvisionPayload($admin, $premise, ['device_name' => $name]))
            ->assertCreated();
    }

    expect(TotemDevice::withoutGlobalScopes()->where('premise_id', $premiseA->id)->count())->toBe(2)
        ->and(TotemDevice::withoutGlobalScopes()->count())->toBe(3);
});

test('a kiosk:punch token cannot reach the employee api', function () {
    $device = TotemDevice::factory()->forPremise(
        Premise::factory()->create(['organization_id' => Organization::factory()->create()->id])
    )->create();
    $token = $device->issuePunchToken();

    foreach (['/api/v1/user', '/api/v1/me/today', '/api/v1/marks'] as $path) {
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson($path)->assertForbidden();
    }

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertForbidden();
});

test('an employee token cannot reach the kiosk api', function () {
    $employee = User::factory()->employee()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    $this->withToken($employee->createToken('pixel')->plainTextToken)
        ->getJson('/api/v1/totem/device')
        ->assertForbidden();
});
