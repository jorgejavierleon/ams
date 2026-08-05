<?php

use App\Models\CostCenter;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function costCenterAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('cost-centers.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('cost-centers.index'))->assertForbidden();
});

// --- Index ---

test('admin can list cost centres with their active employee count', function () {
    $admin = costCenterAdmin();
    $costCenter = CostCenter::factory()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Operaciones',
        'code' => 'CC-001',
    ]);
    User::factory()->count(2)->create([
        'organization_id' => $admin->organization_id,
        'cost_center_id' => $costCenter->id,
        'is_active' => true,
    ]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'cost_center_id' => $costCenter->id,
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('cost-centers.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('cost-centers/index')
                ->has('costCenters.data', 1)
                ->where('costCenters.data.0.name', 'Operaciones')
                ->where('costCenters.data.0.code', 'CC-001')
                ->where('costCenters.data.0.active_users_count', 2),
        );
});

test('the list only shows the current organization cost centres', function () {
    $admin = costCenterAdmin();
    CostCenter::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Mine']);
    CostCenter::factory()->create(['name' => 'Foreign']);

    $this->actingAs($admin)
        ->get(route('cost-centers.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('costCenters.data', 1)
                ->where('costCenters.data.0.name', 'Mine'),
        );
});

test('the list can be searched by name or code', function () {
    $admin = costCenterAdmin();
    CostCenter::factory()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Operaciones',
        'code' => 'CC-777',
    ]);
    CostCenter::factory()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Administración',
        'code' => 'CC-888',
    ]);

    $this->actingAs($admin)
        ->get(route('cost-centers.index', ['search' => 'CC-777']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('costCenters.data', 1)
                ->where('costCenters.data.0.name', 'Operaciones'),
        );
});

// --- Create ---

test('admin can create a cost centre', function () {
    $admin = costCenterAdmin();

    $this->actingAs($admin)
        ->post(route('cost-centers.store'), ['name' => 'Operaciones', 'code' => 'CC-001'])
        ->assertRedirect(route('cost-centers.index'));

    $this->assertDatabaseHas('cost_centers', [
        'organization_id' => $admin->organization_id,
        'name' => 'Operaciones',
        'code' => 'CC-001',
    ]);
});

test('the name is required', function () {
    $admin = costCenterAdmin();

    $this->actingAs($admin)
        ->post(route('cost-centers.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('the accounting code is optional and stored as null when blank', function () {
    $admin = costCenterAdmin();

    $this->actingAs($admin)
        ->post(route('cost-centers.store'), ['name' => 'Sin código', 'code' => '   '])
        ->assertRedirect(route('cost-centers.index'));

    $this->assertDatabaseHas('cost_centers', ['name' => 'Sin código', 'code' => null]);
});

test('two cost centres may both go without a code', function () {
    $admin = costCenterAdmin();

    foreach (['Uno', 'Dos'] as $name) {
        $this->actingAs($admin)
            ->post(route('cost-centers.store'), ['name' => $name, 'code' => ''])
            ->assertRedirect(route('cost-centers.index'));
    }

    expect(CostCenter::query()->whereNull('code')->count())->toBe(2);
});

test('the accounting code is unique within the organization but not across tenants', function () {
    $admin = costCenterAdmin();

    CostCenter::factory()->create([
        'organization_id' => $admin->organization_id,
        'code' => 'CC-001',
    ]);

    // Another tenant already using a code must not block this one.
    CostCenter::factory()->create(['code' => 'CC-002']);

    $this->actingAs($admin)
        ->post(route('cost-centers.store'), ['name' => 'Choca', 'code' => 'CC-001'])
        ->assertSessionHasErrors('code');

    $this->actingAs($admin)
        ->post(route('cost-centers.store'), ['name' => 'Libre', 'code' => 'CC-002'])
        ->assertRedirect(route('cost-centers.index'));
});

// --- Update ---

test('admin can rename a cost centre and keep its own code', function () {
    $admin = costCenterAdmin();
    $costCenter = CostCenter::factory()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Antiguo',
        'code' => 'CC-001',
    ]);

    $this->actingAs($admin)
        ->put(route('cost-centers.update', $costCenter), ['name' => 'Nuevo', 'code' => 'CC-001'])
        ->assertRedirect(route('cost-centers.index'));

    expect($costCenter->fresh()->name)->toBe('Nuevo');
    expect($costCenter->fresh()->code)->toBe('CC-001');
});

test('admin cannot update a cost centre from another organization', function () {
    $admin = costCenterAdmin();
    $foreign = CostCenter::factory()->create();

    $this->actingAs($admin)
        ->put(route('cost-centers.update', $foreign), ['name' => 'Hijacked'])
        ->assertNotFound();
});

// --- Delete ---

test('admin can delete an unused cost centre', function () {
    $admin = costCenterAdmin();
    $costCenter = CostCenter::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->delete(route('cost-centers.destroy', $costCenter))
        ->assertRedirect(route('cost-centers.index'));

    $this->assertDatabaseMissing('cost_centers', ['id' => $costCenter->id]);
});

test('a cost centre with active employees cannot be deleted', function () {
    $admin = costCenterAdmin();
    $costCenter = CostCenter::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'cost_center_id' => $costCenter->id,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('cost-centers.destroy', $costCenter))
        ->assertRedirect(route('cost-centers.index'));

    $this->assertDatabaseHas('cost_centers', ['id' => $costCenter->id]);
});
