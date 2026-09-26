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
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Owner (KOL-133): admin alone no longer bypasses OvertimeAuthorizationPolicy's
    // approve/revoke, so this test's "admin" is also the organization's Owner.
    // owner_id is deliberately not fillable outside TransferOrganizationOwnership,
    // so an existing organization is force-filled instead of mass-assigned.
    if ($organization === null) {
        $organization = Organization::factory()->ownedBy($admin)->create();
    } elseif ($organization->owner_id === null) {
        $organization->forceFill(['owner_id' => $admin->id])->save();
    }

    $admin->update(['organization_id' => $organization->id]);

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
        ->and($supervisor->can('revoke', $authorization))->toBeTrue();
});

test('a supervisor is refused for overtime outside their team', function () {
    $organization = Organization::factory()->create();
    $supervisor = overtimeSectionSupervisor($organization);
    // Employee reports to nobody (not this supervisor).
    $employee = overtimeSectionEmployee($organization);

    $authorization = overtimeSectionRecordFor($employee);

    expect($supervisor->can('approve', $authorization))->toBeFalse()
        ->and($supervisor->can('revoke', $authorization))->toBeFalse();
});

test('an admin who is also the organization Owner can reach the overtime section and decide any record', function () {
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
        ->get(route('settings-overtime.edit'))
        ->assertForbidden();
});
