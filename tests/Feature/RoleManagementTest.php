<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

// --- Access control ---

it('redirects unauthenticated users from roles index', function () {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

it('blocks non-admin users from accessing roles index', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
});

it('blocks non-admin users from accessing role detail', function () {
    $role = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('roles.show', $role))->assertForbidden();
});

it('blocks non-admin users from updating role permissions', function () {
    $role = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->put(route('roles.update', $role), ['permissions' => []])->assertForbidden();
});

it('blocks non-admin users from assigning user roles', function () {
    $target = User::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('users.roles', $target))->assertForbidden();
});

// --- Protected roles ---

it('admin cannot view the admin role detail', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'admin')->first();

    $this->actingAs($admin)->get(route('roles.show', $role))->assertForbidden();
});

it('admin cannot view the dt role detail', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'dt', 'guard_name' => 'web']);

    $this->actingAs($admin)->get(route('roles.show', $role))->assertForbidden();
});

it('admin cannot view the saas role detail', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'saas', 'guard_name' => 'web']);

    $this->actingAs($admin)->get(route('roles.show', $role))->assertForbidden();
});

it('admin cannot update permissions on a protected role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'admin')->first();

    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['permissions' => []])
        ->assertForbidden();
});

it('roles index does not include protected roles', function () {
    Role::firstOrCreate(['name' => 'dt', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'saas', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $names = collect($page->toArray()['props']['roles']['data'])->pluck('name')->all();
            expect($names)->toContain('editor')
                ->and($names)->not->toContain('admin')
                ->and($names)->not->toContain('dt')
                ->and($names)->not->toContain('saas');
        });
});

// --- Roles index ---

it('admin can view roles list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('roles/index')
                ->has('roles.data')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
        );
});

it('roles index can be searched by name', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->get(route('roles.index', ['search' => 'edit']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('roles.data', 1)
                ->where('roles.data.0.name', 'editor')
        );
});

it('roles index can be sorted by name descending', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Role::firstOrCreate(['name' => 'alpha', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'omega', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->get(route('roles.index', ['sort' => 'name', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $names = collect($page->toArray()['props']['roles']['data'])->pluck('name')->all();
            expect(array_search('omega', $names))->toBeLessThan(array_search('alpha', $names));
        });
});

it('roles index ignores a disallowed sort column', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->get(route('roles.index', ['sort' => 'id', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
        );
});

// --- Role show ---

it('admin can view role detail with permission groups', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->get(route('roles.show', $role))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('roles/show')
                ->has('role')
                ->has('permissionGroups')
        );
});

// --- Role permission update ---

it('admin can sync permissions for a role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);

    $p1 = Permission::firstOrCreate(['name' => 'view_employee', 'guard_name' => 'web']);
    $p2 = Permission::firstOrCreate(['name' => 'create_employee', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['permissions' => [$p1->id]])
        ->assertRedirect(route('roles.show', $role));

    expect($role->fresh()->hasPermissionTo('view_employee'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('create_employee'))->toBeFalse();
});

it('syncs permissions submitted as string ids from the form', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);

    $p1 = Permission::firstOrCreate(['name' => 'ViewTeam:Leave', 'guard_name' => 'web']);
    $p2 = Permission::firstOrCreate(['name' => 'ApproveTeam:Leave', 'guard_name' => 'web']);
    $role->givePermissionTo([$p1, $p2]);

    // The roles form submits permission ids as strings; syncPermissions() would
    // otherwise treat a string id as a permission name and blow up.
    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['permissions' => [(string) $p1->id]])
        ->assertRedirect(route('roles.show', $role));

    expect($role->fresh()->hasPermissionTo('ViewTeam:Leave'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('ApproveTeam:Leave'))->toBeFalse();
});

it('admin can remove all permissions from a role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    $permission = Permission::firstOrCreate(['name' => 'view_employee', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['permissions' => []])
        ->assertRedirect(route('roles.show', $role));

    expect($role->fresh()->permissions)->toBeEmpty();
});

it('validates that permission ids must exist in the database', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['permissions' => [99999]])
        ->assertSessionHasErrors('permissions.0');
});

// --- User role assignment ---

it('admin can view user role assignment page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('users.roles', $target))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('users/roles')
                ->has('user')
                ->has('roles')
        );
});

it('admin can assign roles to a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();
    $role = Role::where('name', 'employee')->first();

    $this->actingAs($admin)
        ->put(route('users.roles.update', $target), ['roles' => [$role->id]])
        ->assertRedirect(route('users.roles', $target));

    expect($target->fresh()->hasRole('employee'))->toBeTrue();
});

it('admin can remove all roles from a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole('employee');

    $this->actingAs($admin)
        ->put(route('users.roles.update', $target), ['roles' => []])
        ->assertRedirect(route('users.roles', $target));

    expect($target->fresh()->roles)->toBeEmpty();
});

it('validates that role ids must exist in the database', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('users.roles.update', $target), ['roles' => [99999]])
        ->assertSessionHasErrors('roles.0');
});
