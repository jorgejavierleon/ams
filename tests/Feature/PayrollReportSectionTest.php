<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('an admin can reach the payroll reports section', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('payroll-reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('payroll-reports/index'));
});

test('a user without View:PayrollReport is forbidden from the payroll reports section by direct URL', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('payroll-reports.index'))
        ->assertForbidden();
});

test('an employee without the permission is forbidden from the payroll reports section', function () {
    $employee = User::factory()->employee()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('payroll-reports.index'))
        ->assertForbidden();
});

test('shared auth permissions include View:PayrollReport for an admin, driving nav visibility', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('View:PayrollReport'))
        );
});

test('shared auth permissions exclude View:PayrollReport for an employee, hiding the nav item', function () {
    $employee = User::factory()->employee()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', fn ($permissions) => ! collect($permissions)->contains('View:PayrollReport'))
        );
});
