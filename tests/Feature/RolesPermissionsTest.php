<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);

    Route::middleware(['auth', 'role:admin'])
        ->get('/_test/admin-only', fn () => response('ok'));
});

it('seeds the four expected roles', function () {
    $this->seed(RoleSeeder::class);

    foreach (['admin', 'employee', 'dt', 'saas'] as $role) {
        expect(Role::where('name', $role)->exists())->toBeTrue();
    }
});

it('allows a user with the admin role to access admin-only routes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/_test/admin-only')
        ->assertOk();
});

it('blocks a user with the employee role from admin-only routes', function () {
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get('/_test/admin-only')
        ->assertStatus(403);
});

it('blocks an unauthenticated user from admin-only routes', function () {
    $this->get('/_test/admin-only')
        ->assertRedirect(route('login'));
});

it('assigns and checks roles via hasRole', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->hasRole('employee'))->toBeFalse();
});

it('clears permission cache without error', function () {
    $exitCode = Artisan::call('permission:cache-reset');

    expect($exitCode)->toBe(0);
});

it('seeds Import:Employee to the admin role only', function () {
    $this->seed(RoleSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $employee = User::factory()->create();
    $employee->assignRole('employee');

    expect($admin->hasPermissionTo('Import:Employee'))->toBeTrue()
        ->and($employee->hasPermissionTo('Import:Employee'))->toBeFalse();
});

it('seeds the admin role with every permission the seeder defines, not just ADMIN_PERMISSIONS', function () {
    $this->seed(RoleSeeder::class);

    $adminRole = Role::findByName('admin');
    $allPermissionNames = Permission::pluck('name')->sort()->values()->all();

    expect($adminRole->permissions->pluck('name')->sort()->values()->all())->toBe($allPermissionNames)
        // Sanity check that this is actually exercising employee/supervisor
        // permissions admin never used to hold directly.
        ->and($adminRole->hasPermissionTo('RequestOwn:Leave'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('ViewTeam:Leave'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('ApproveTeam:OvertimeAuthorization'))->toBeTrue();
});
