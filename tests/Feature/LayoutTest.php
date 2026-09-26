<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('dashboard renders the correct Inertia component for authenticated users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

test('unauthenticated requests to authenticated routes redirect to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('shared Inertia props include auth user, flash data, and permissions', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->has('auth.permissions')
            ->has('flash.success')
            ->has('flash.error')
            ->has('flash.warning')
        );
});

test('shared Inertia auth.canViewEmployee reflects View:Employee/Manage:Employee, not the admin role', function () {
    // KOL-133.6: the employee-link prop tracks the same permissions that
    // gate employees.* routes (KOL-133.4), independent of role name, so a
    // role can hold it without being "admin" and admin can lose it.
    Permission::firstOrCreate(['name' => 'View:Employee', 'guard_name' => 'web']);

    $supervisorRole = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
    $supervisorRole->givePermissionTo('View:Employee');

    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canViewEmployee', true));

    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->givePermissionTo('View:Employee');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canViewEmployee', true));

    $adminRole->revokePermissionTo('View:Employee');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canViewEmployee', false));
});

test('flash success message is present in Inertia shared data after redirect', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['success' => 'Record saved.'])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('flash.success', 'Record saved.')
        );
});
