<?php

use App\Models\Organization;
use App\Models\Premise;
use App\Models\TotemDevice;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function totemManagementAdmin(?Organization $organization = null): User
{
    $admin = User::factory()->create([
        'organization_id' => ($organization ?? Organization::factory()->create())->id,
    ]);
    $admin->assignRole('admin');

    return $admin;
}

test('unauthenticated users are redirected to login', function () {
    $this->get(route('totem-devices.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('totem-devices.index'))->assertForbidden();
});

test('admin lists only the devices of their own organization', function () {
    $admin = totemManagementAdmin();
    $premise = Premise::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Sucursal Centro']);
    $own = TotemDevice::factory()->forPremise($premise)->create(['name' => 'Entrada']);
    $own->issuePunchToken();
    TotemDevice::factory()->forPremise(Premise::factory()->create())->create(['name' => 'Ajeno']);

    $this->actingAs($admin)
        ->get(route('totem-devices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('totem-devices/index')
            ->has('devices', 1)
            ->where('devices.0.name', 'Entrada')
            ->where('devices.0.premise', 'Sucursal Centro')
            ->has('devices.0.created_at')
            ->where('devices.0.last_used_at', null));
});

test('revoking a device invalidates its token', function () {
    $admin = totemManagementAdmin();
    $device = TotemDevice::factory()->forPremise(
        Premise::factory()->create(['organization_id' => $admin->organization_id])
    )->create();
    $token = $device->issuePunchToken();

    $this->withToken($token)->getJson('/api/v1/totem/device')->assertOk();

    $this->actingAs($admin)
        ->delete(route('totem-devices.destroy', $device))
        ->assertRedirect(route('totem-devices.index'));

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/totem/device')->assertUnauthorized();

    $this->actingAs($admin)
        ->get(route('totem-devices.index'))
        ->assertInertia(fn ($page) => $page->has('devices', 0));
});

test('an admin cannot revoke another organization\'s device', function () {
    $admin = totemManagementAdmin();
    $foreign = TotemDevice::factory()->forPremise(Premise::factory()->create())->create();

    $this->actingAs($admin)
        ->delete(route('totem-devices.destroy', $foreign->id))
        ->assertNotFound();
});
