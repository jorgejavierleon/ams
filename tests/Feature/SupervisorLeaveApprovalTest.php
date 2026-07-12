<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\LeaveApproved;
use App\Notifications\LeaveRejected;
use App\Notifications\LeaveRequestSubmitted;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed every role + permission (admin, employee, supervisor, ...).
    $this->seed(RoleSeeder::class);
});

function supervisorAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function supervisorTeamEmployee(Organization $organization, array $attributes = []): User
{
    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        ...$attributes,
    ]);
}

/**
 * A supervisor employee. When $canApprove is false we mimic an admin who has
 * revoked the team-approval permission from the shared supervisor role.
 */
function teamSupervisor(Organization $organization, bool $canApprove = true): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    if (! $canApprove) {
        Role::findByName('supervisor')->revokePermissionTo('ApproveTeam:Leave');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    return $supervisor;
}

// --- Approval authority ---

test('a supervisor can approve their own team member leave', function () {
    $organization = Organization::factory()->create();
    $supervisor = teamSupervisor($organization);
    $employee = supervisorTeamEmployee($organization, ['supervisor_id' => $supervisor->id]);

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
        'created_by' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('leaves.approve', $leave))
        ->assertRedirect();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Approved)
        ->and($leave->approved_by)->toBe($supervisor->id);
});

test('a supervisor cannot approve a leave outside their team', function () {
    $organization = Organization::factory()->create();
    $supervisor = teamSupervisor($organization);
    // Employee reports to nobody (not this supervisor).
    $employee = supervisorTeamEmployee($organization);

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
        'created_by' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('leaves.approve', $leave))
        ->assertForbidden();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Pending);
});

test('a supervisor cannot approve when the team-approval permission is revoked', function () {
    $organization = Organization::factory()->create();
    $supervisor = teamSupervisor($organization, canApprove: false);
    $employee = supervisorTeamEmployee($organization, ['supervisor_id' => $supervisor->id]);

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
        'created_by' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('leaves.approve', $leave))
        ->assertForbidden();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Pending);
});

test('a supervisor can reject their own team member leave', function () {
    $organization = Organization::factory()->create();
    $supervisor = teamSupervisor($organization);
    $employee = supervisorTeamEmployee($organization, ['supervisor_id' => $supervisor->id]);

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
        'created_by' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('leaves.reject', $leave))
        ->assertRedirect();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Rejected);
});

// --- Team-scoped index ---

test('a supervisor only sees their own team on the leaves index', function () {
    $organization = Organization::factory()->create();
    $supervisor = teamSupervisor($organization);
    $teamMember = supervisorTeamEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $otherEmployee = supervisorTeamEmployee($organization);

    Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $teamMember->id,
        'created_by' => $teamMember->id,
    ]);
    Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $otherEmployee->id,
        'created_by' => $otherEmployee->id,
    ]);

    $this->actingAs($supervisor)
        ->get(route('leaves.index'))
        ->assertInertia(fn ($page) => $page
            ->component('leaves/index')
            ->has('leaves.data', 1)
            ->where('leaves.data.0.employee', $teamMember->name)
            ->where('can.create', false)
            ->where('can.delete', false)
            ->where('can.approve', true));
});

test('a supervisor without the approval permission cannot see approve actions', function () {
    $organization = Organization::factory()->create();
    $supervisor = teamSupervisor($organization, canApprove: false);
    $teamMember = supervisorTeamEmployee($organization, ['supervisor_id' => $supervisor->id]);

    Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $teamMember->id,
        'created_by' => $teamMember->id,
    ]);

    $this->actingAs($supervisor)
        ->get(route('leaves.index'))
        ->assertInertia(fn ($page) => $page
            ->component('leaves/index')
            ->has('leaves.data', 1)
            ->where('can.approve', false));
});

test('an admin sees every leave and the create/delete capabilities', function () {
    $admin = supervisorAdmin();
    $organization = $admin->organization;
    $employee = supervisorTeamEmployee($organization);

    Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('leaves.index'))
        ->assertInertia(fn ($page) => $page
            ->where('can.create', true)
            ->where('can.delete', true)
            ->where('can.approve', true));
});

test('a supervisor cannot create or delete leaves', function () {
    $organization = Organization::factory()->create();
    $supervisor = teamSupervisor($organization);
    $employee = supervisorTeamEmployee($organization, ['supervisor_id' => $supervisor->id]);

    $leave = Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'created_by' => $employee->id,
    ]);

    // Creating-for-others and deleting stay behind the admin-only routes.
    $this->actingAs($supervisor)->get(route('leaves.create'))->assertForbidden();
    $this->actingAs($supervisor)->delete(route('leaves.destroy', $leave))->assertForbidden();
});

// --- Notifications ---

test('submitting a leave notifies the supervisor and copies admins', function () {
    Notification::fake();

    $admin = supervisorAdmin();
    $organization = $admin->organization;
    $supervisor = teamSupervisor($organization);
    $employee = supervisorTeamEmployee($organization, ['supervisor_id' => $supervisor->id]);

    $this->actingAs($employee)
        ->post(route('my.leaves.store'), [
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'half_day' => false,
            'business_days_requested' => 2,
        ])
        ->assertRedirect(route('my.leaves.index'));

    Notification::assertSentTo($supervisor, LeaveRequestSubmitted::class);
    Notification::assertSentTo($admin, LeaveRequestSubmitted::class);
    Notification::assertNotSentTo($employee, LeaveRequestSubmitted::class);
});

test('submission falls back to admins when the supervisor cannot approve', function () {
    Notification::fake();

    $admin = supervisorAdmin();
    $organization = $admin->organization;
    $supervisor = teamSupervisor($organization, canApprove: false);
    $employee = supervisorTeamEmployee($organization, ['supervisor_id' => $supervisor->id]);

    $this->actingAs($employee)
        ->post(route('my.leaves.store'), [
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'half_day' => false,
            'business_days_requested' => 2,
        ])
        ->assertRedirect(route('my.leaves.index'));

    Notification::assertSentTo($admin, LeaveRequestSubmitted::class);
    Notification::assertNotSentTo($supervisor, LeaveRequestSubmitted::class);
});

test('approving a leave notifies the requesting employee', function () {
    Notification::fake();

    $admin = supervisorAdmin();
    $organization = $admin->organization;
    $employee = supervisorTeamEmployee($organization);

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->post(route('leaves.approve', $leave))->assertRedirect();

    Notification::assertSentTo($employee, LeaveApproved::class);
});

test('rejecting a leave notifies the requesting employee', function () {
    Notification::fake();

    $admin = supervisorAdmin();
    $organization = $admin->organization;
    $employee = supervisorTeamEmployee($organization);

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->post(route('leaves.reject', $leave))->assertRedirect();

    Notification::assertSentTo($employee, LeaveRejected::class);
});
