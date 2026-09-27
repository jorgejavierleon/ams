<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // roles.index/show/update are gated by Manage:Role, granted to admin by
    // RoleSeeder — seed it so the "admin" role this file builds actually
    // holds that permission.
    $this->seed(RoleSeeder::class);
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

// --- Protected roles ---

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

    $role = Role::firstOrCreate(['name' => 'dt', 'guard_name' => 'web']);

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
                ->and($names)->toContain('admin')
                ->and($names)->not->toContain('dt')
                ->and($names)->not->toContain('saas');
        });
});

// --- The admin role is no longer protected (KOL-133.3) ---

it('admin can view the admin role detail', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'admin')->first();

    $this->actingAs($admin)
        ->get(route('roles.show', $role))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('roles/show')
                ->where('role.name', 'admin')
        );
});

it('admin can edit the admin role permission set', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'admin')->first();
    $permission = Permission::firstOrCreate(['name' => 'view_employee', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => [$permission->id]])
        ->assertRedirect(route('roles.show', $role));

    expect($role->fresh()->hasPermissionTo('view_employee'))->toBeTrue();
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

// --- Users-per-role counts ---

it('roles index shows zero users for a role with no one assigned', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $editor = collect($page->toArray()['props']['roles']['data'])
                ->firstWhere('name', 'editor');

            expect($editor['users_count'])->toBe(0)
                ->and($editor['avatars'])->toBe([]);
        });
});

it('roles index counts a single user holding a role', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $employee->assignRole('employee');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(function ($page) use ($employee) {
            $role = collect($page->toArray()['props']['roles']['data'])
                ->firstWhere('name', 'employee');

            expect($role['users_count'])->toBe(1)
                ->and(collect($role['avatars'])->pluck('id')->all())->toBe([$employee->id]);
        });
});

it('roles index counts multiple users holding a role', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $employees = User::factory()->count(3)->create(['organization_id' => $organization->id]);
    $employees->each(fn (User $user) => $user->assignRole('employee'));

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $role = collect($page->toArray()['props']['roles']['data'])
                ->firstWhere('name', 'employee');

            expect($role['users_count'])->toBe(3);
        });
});

it('roles index never counts a user from another organization', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $ownEmployee = User::factory()->create(['organization_id' => $organization->id]);
    $ownEmployee->assignRole('employee');

    $foreignEmployee = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $foreignEmployee->assignRole('employee');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(function ($page) use ($ownEmployee) {
            $role = collect($page->toArray()['props']['roles']['data'])
                ->firstWhere('name', 'employee');

            expect($role['users_count'])->toBe(1)
                ->and(collect($role['avatars'])->pluck('id')->all())->toBe([$ownEmployee->id]);
        });
});

it('all_users_count reflects every organization, unlike the org-scoped users_count', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $ownEmployee = User::factory()->create(['organization_id' => $organization->id]);
    $ownEmployee->assignRole('employee');

    $foreignEmployee = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $foreignEmployee->assignRole('employee');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $role = collect($page->toArray()['props']['roles']['data'])
                ->firstWhere('name', 'employee');

            expect($role['users_count'])->toBe(1)
                ->and($role['all_users_count'])->toBe(2);
        });
});

it('roles index can be sorted by users count', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $employees = User::factory()->count(2)->create(['organization_id' => $organization->id]);
    $employees->each(fn (User $user) => $user->assignRole('employee'));

    $this->actingAs($admin)
        ->get(route('roles.index', ['sort' => 'users_count', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('roles.data.0.name', 'employee')
                ->where('filters.sort', 'users_count')
                ->where('filters.direction', 'desc')
        );
});

// --- Localized labels ---

it('roles index exposes localized role labels alongside the raw name', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $employee = collect($page->toArray()['props']['roles']['data'])
                ->firstWhere('name', 'employee');

            expect($employee['name'])->toBe('employee')
                ->and($employee['label'])->toBe('Empleado');
        });
});

it('role detail groups permissions under localized group and permission labels', function () {
    $this->seed(RoleSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'employee')->first();

    $this->actingAs($admin)
        ->get(route('roles.show', $role))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['role']['label'])->toBe('Empleado');

            $attendance = collect($props['permissionGroups'])->firstWhere('group', 'Asistencia');
            expect($attendance)->not->toBeNull();

            $permission = collect($attendance['permissions'])->firstWhere('name', 'ViewOwn:Mark');
            expect($permission['label'])->toBe('Ver marcas propias');
        });
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
        ->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => [$p1->id]])
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
        ->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => [(string) $p1->id]])
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
        ->put(route('roles.update', $role), ['name' => $role->name, 'permissions' => []])
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

// --- Role creation ---

it('blocks non-admin users from creating roles', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)
        ->post(route('roles.store'), ['name' => 'editor', 'permissions' => []])
        ->assertForbidden();
});

it('admin can create a custom role with an initial set of permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $permission = Permission::firstOrCreate(['name' => 'view_employee', 'guard_name' => 'web']);

    $response = $this->actingAs($admin)->post(route('roles.store'), [
        'name' => 'editor',
        'permissions' => [$permission->id],
    ]);

    $role = Role::where('name', 'editor')->first();

    expect($role)->not->toBeNull();
    $response->assertRedirect(route('roles.show', $role));
    expect($role->hasPermissionTo('view_employee'))->toBeTrue();
});

it('rejects creating a role with a name that already exists', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('roles.store'), ['name' => 'employee', 'permissions' => []])
        ->assertSessionHasErrors('name');

    expect(Role::where('name', 'employee')->count())->toBe(1);
});

it('renders the role create page with permission groups', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('roles.create'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('roles/create')
                ->has('permissionGroups')
        );
});

// --- Role renaming ---

it('admin can rename a role while editing its permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    $permission = Permission::firstOrCreate(['name' => 'view_employee', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->put(route('roles.update', $role), [
            'name' => 'content-editor',
            'permissions' => [$permission->id],
        ])
        ->assertRedirect(route('roles.show', $role));

    $role->refresh();
    expect($role->name)->toBe('content-editor')
        ->and($role->hasPermissionTo('view_employee'))->toBeTrue();
});

it('rejects renaming a role to a name that already exists', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['name' => 'viewer', 'permissions' => []])
        ->assertSessionHasErrors('name');

    expect($role->fresh()->name)->toBe('editor');
});

// --- Role deletion ---

it('blocks non-admin users from deleting roles', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($user)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();
});

it('admin cannot delete a protected role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'dt', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    expect(Role::where('name', 'dt')->exists())->toBeTrue();
});

it('admin can delete a role that no one holds', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::where('name', 'editor')->exists())->toBeFalse();
});

it('deleting a role detaches it from every user who held it without deleting them', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    $holder = User::factory()->create(['organization_id' => $organization->id]);
    $holder->assignRole(['employee', 'editor']);

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::where('name', 'editor')->exists())->toBeFalse()
        ->and(User::find($holder->id))->not->toBeNull()
        ->and($holder->fresh()->hasRole('editor'))->toBeFalse()
        ->and($holder->fresh()->hasRole('employee'))->toBeTrue();
});

// --- System role protection (KOL-135.3) ---

it('cannot delete the admin role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'admin')->first();

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    expect(Role::where('name', 'admin')->exists())->toBeTrue();
});

it('cannot delete the employee role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'employee')->first();

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    expect(Role::where('name', 'employee')->exists())->toBeTrue();
});

it('cannot delete the supervisor role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'supervisor')->first();

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    expect(Role::where('name', 'supervisor')->exists())->toBeTrue();
});

it('cannot rename a system role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'employee')->first();

    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['name' => 'not-employee', 'permissions' => []])
        ->assertSessionHasErrors('name');

    expect($role->fresh()->name)->toBe('employee');
});

it('can still edit a system role permissions as long as the name is unchanged', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'employee')->first();
    $permission = Permission::firstOrCreate(['name' => 'view_employee', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['name' => 'employee', 'permissions' => [$permission->id]])
        ->assertRedirect(route('roles.show', $role));

    expect($role->fresh()->name)->toBe('employee')
        ->and($role->fresh()->hasPermissionTo('view_employee'))->toBeTrue();
});

it('custom roles remain freely renameable and deletable', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->put(route('roles.update', $role), ['name' => 'content-editor', 'permissions' => []])
        ->assertRedirect(route('roles.show', $role));

    expect($role->fresh()->name)->toBe('content-editor');

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::where('id', $role->id)->exists())->toBeFalse();
});

// --- Restore default permissions ---

it('blocks non-admin users from restoring default permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $role = Role::where('name', 'employee')->first();

    $this->actingAs($user)
        ->post(route('roles.restore-defaults', $role))
        ->assertForbidden();
});

it('cannot restore defaults on a non-system role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->post(route('roles.restore-defaults', $role))
        ->assertForbidden();
});

it('restores the employee role to its RoleSeeder default permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'employee')->first();
    $extra = Permission::firstOrCreate(['name' => 'view_employee', 'guard_name' => 'web']);
    $role->givePermissionTo($extra);

    $this->actingAs($admin)
        ->post(route('roles.restore-defaults', $role))
        ->assertRedirect(route('roles.show', $role));

    $defaultPermissionNames = collect(RoleSeeder::defaultPermissionsFor('employee'))->sort()->values();
    $rolePermissionNames = $role->fresh()->permissions->pluck('name')->sort()->values();

    expect($rolePermissionNames->all())->toBe($defaultPermissionNames->all());
});

// --- Role cloning (KOL-135.4) ---

it('blocks non-admin users from cloning roles', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($user)
        ->post(route('roles.clone', $role))
        ->assertForbidden();

    expect(Role::where('name', 'editor (copy)')->exists())->toBeFalse();
});

it('admin cannot clone a protected role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'dt', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->post(route('roles.clone', $role))
        ->assertForbidden();

    expect(Role::where('name', 'dt (copy)')->exists())->toBeFalse();
});

it('admin can clone a custom role with its current permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    $permission = Permission::firstOrCreate(['name' => 'view_employee', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $response = $this->actingAs($admin)->post(route('roles.clone', $role));

    $clone = Role::where('name', 'editor (copy)')->first();

    expect($clone)->not->toBeNull();
    $response->assertRedirect(route('roles.show', $clone));
    expect($clone->hasPermissionTo('view_employee'))->toBeTrue();
});

it('dedupes the clone name when it is already taken', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'editor (copy)', 'guard_name' => 'web']);

    $this->actingAs($admin)->post(route('roles.clone', $role));

    expect(Role::where('name', 'editor (copy 2)')->exists())->toBeTrue();
});

it('truncates a long source name so the cloned name never exceeds the column length', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $longName = str_repeat('a', 255);
    $role = Role::firstOrCreate(['name' => $longName, 'guard_name' => 'web']);

    $this->actingAs($admin)->post(route('roles.clone', $role))->assertRedirect();

    $clone = Role::where('name', '!=', $longName)->latest('id')->first();

    expect($clone)->not->toBeNull()
        ->and(mb_strlen($clone->name))->toBeLessThanOrEqual(255)
        ->and($clone->name)->toEndWith(' (copy)');
});

it('cloning a system role produces an ordinary role that can be renamed and deleted', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'admin')->first();

    $this->actingAs($admin)->post(route('roles.clone', $role));

    $clone = Role::where('name', 'admin (copy)')->first();
    expect($clone)->not->toBeNull();

    $this->actingAs($admin)
        ->put(route('roles.update', $clone), ['name' => 'admin-lite', 'permissions' => []])
        ->assertRedirect(route('roles.show', $clone));

    $clone->refresh();
    expect($clone->name)->toBe('admin-lite');

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $clone))
        ->assertRedirect(route('roles.index'));

    expect(Role::where('id', $clone->id)->exists())->toBeFalse();
});

it('restores the admin role to its RoleSeeder default permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::where('name', 'admin')->first();
    // Revoke a permission other than Manage:Role: the acting user's own
    // access to this very endpoint is gated by holding Manage:Role through
    // the admin role, so stripping that one would lock them out before the
    // restore could run.
    $role->revokePermissionTo('View:Employee');

    $this->actingAs($admin)
        ->post(route('roles.restore-defaults', $role))
        ->assertRedirect(route('roles.show', $role));

    expect($role->fresh()->hasPermissionTo('View:Employee'))->toBeTrue();
});
