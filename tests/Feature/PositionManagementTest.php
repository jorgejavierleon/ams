<?php

use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function admin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('positions.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('positions.index'))->assertForbidden();
});

// --- Index ---

test('admin can list positions with active employee counts', function () {
    $admin = admin();
    $position = Position::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'position_id' => $position->id,
        'is_active' => true,
    ]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'position_id' => $position->id,
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('positions.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('positions/index')
                ->has('positions.data', 1)
                ->where('positions.data.0.active_users_count', 1),
        );
});

test('positions index only shows the current organization positions', function () {
    $admin = admin();
    Position::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Supervisor']);
    Position::factory()->create(['name' => 'Foreign role']);

    $this->actingAs($admin)
        ->get(route('positions.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('positions.data', 1)
                ->where('positions.data.0.name', 'Supervisor'),
        );
});

test('positions index can be searched by name', function () {
    $admin = admin();
    Position::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Operario']);
    Position::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Supervisor']);

    $this->actingAs($admin)
        ->get(route('positions.index', ['search' => 'Oper']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('positions.data', 1)
                ->where('positions.data.0.name', 'Operario'),
        );
});

// --- Create ---

test('admin can create a position scoped to their organization', function () {
    $admin = admin();

    $this->actingAs($admin)
        ->post(route('positions.store'), ['name' => 'Supervisor'])
        ->assertRedirect(route('positions.index'));

    $this->assertDatabaseHas('positions', [
        'name' => 'Supervisor',
        'organization_id' => $admin->organization_id,
    ]);
});

test('creating a position requires a name', function () {
    $this->actingAs(admin())
        ->post(route('positions.store'), [])
        ->assertSessionHasErrors('name');
});

// --- Update ---

test('admin can rename a position', function () {
    $admin = admin();
    $position = Position::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Old']);

    $this->actingAs($admin)
        ->patch(route('positions.update', $position), ['name' => 'New'])
        ->assertRedirect(route('positions.index'));

    expect($position->fresh()->name)->toBe('New');
});

test('admin cannot access a position from another organization', function () {
    $admin = admin();
    $foreign = Position::factory()->create();

    $this->actingAs($admin)
        ->get(route('positions.show', $foreign))
        ->assertNotFound();
});

// --- Show ---

test('admin can view a position with its assigned employees', function () {
    $admin = admin();
    $position = Position::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->count(2)->create([
        'organization_id' => $admin->organization_id,
        'position_id' => $position->id,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('positions.show', $position))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('positions/show')
                ->where('position.id', $position->id)
                ->has('employees.data', 2),
        );
});

// --- Delete ---

test('a position without active employees can be deleted', function () {
    $admin = admin();
    $position = Position::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->delete(route('positions.destroy', $position))
        ->assertRedirect(route('positions.index'));

    $this->assertDatabaseMissing('positions', ['id' => $position->id]);
});

test('a position with active employees cannot be deleted', function () {
    $admin = admin();
    $position = Position::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'position_id' => $position->id,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('positions.destroy', $position))
        ->assertRedirect(route('positions.index'));

    $this->assertDatabaseHas('positions', ['id' => $position->id]);
});

test('a position whose only employees are inactive can be deleted', function () {
    $admin = admin();
    $position = Position::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'position_id' => $position->id,
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->delete(route('positions.destroy', $position))
        ->assertRedirect(route('positions.index'));

    $this->assertDatabaseMissing('positions', ['id' => $position->id]);
});
