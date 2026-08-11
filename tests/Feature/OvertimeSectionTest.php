<?php

use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed every role + permission (admin, employee, supervisor, ...).
    $this->seed(RoleSeeder::class);
});

function overtimeSectionAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function overtimeSectionEmployee(Organization $organization): User
{
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $employee->assignRole('employee');

    return $employee;
}

function overtimeSectionSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

/**
 * A pending overtime authorization for an employee, ready to be decided.
 */
function overtimeSectionRecordFor(User $employee): OvertimeAuthorization
{
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);

    return OvertimeAuthorization::openFor($workday);
}

test('an employee holding the view-own permission can reach the overtime section', function () {
    $organization = Organization::factory()->create();
    $employee = overtimeSectionEmployee($organization);

    $this->actingAs($employee)
        ->get(route('overtime.index'))
        ->assertOk();
});

test('a supervisor can decide their own direct report overtime', function () {
    $organization = Organization::factory()->create();
    $supervisor = overtimeSectionSupervisor($organization);
    $employee = overtimeSectionEmployee($organization);
    $employee->update(['supervisor_id' => $supervisor->id]);

    $authorization = overtimeSectionRecordFor($employee);

    expect($supervisor->can('approve', $authorization))->toBeTrue()
        ->and($supervisor->can('object', $authorization))->toBeTrue();
});

test('a supervisor is refused for overtime outside their team', function () {
    $organization = Organization::factory()->create();
    $supervisor = overtimeSectionSupervisor($organization);
    // Employee reports to nobody (not this supervisor).
    $employee = overtimeSectionEmployee($organization);

    $authorization = overtimeSectionRecordFor($employee);

    expect($supervisor->can('approve', $authorization))->toBeFalse()
        ->and($supervisor->can('object', $authorization))->toBeFalse();
});

test('an admin can reach the overtime section and decide any record via the super-admin gate', function () {
    $admin = overtimeSectionAdmin();
    $organization = $admin->organization;
    $employee = overtimeSectionEmployee($organization);

    $authorization = overtimeSectionRecordFor($employee);

    $this->actingAs($admin)
        ->get(route('overtime.index'))
        ->assertOk();

    expect($admin->can('approve', $authorization))->toBeTrue();
});

test('a user with no overtime permission at all is forbidden from the overtime section', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('overtime.index'))
        ->assertForbidden();
});

test('a supervisor cannot reach the tenant overtime policy configuration', function () {
    $organization = Organization::factory()->create();
    $supervisor = overtimeSectionSupervisor($organization);

    $this->actingAs($supervisor)
        ->get(route('organization-settings.edit'))
        ->assertForbidden();
});
