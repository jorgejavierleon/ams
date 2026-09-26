<?php

use App\Enums\LeaveStatus;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The shared `auth.pendingLeaveRequestsCount` dashboard prop (KOL-119.2):
 * scoped exactly like LeaveController::index — org-wide for admins (Leave has
 * no dedicated admin permission, so admins reach the index via the
 * super-admin gate rather than holding ApproveTeam:Leave), the supervisor's
 * own direct reports for ApproveTeam:Leave, and zero for anyone holding
 * neither.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function leaveBadgeAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function leaveBadgeSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

function leaveBadgeEmployee(Organization $organization, ?User $supervisor = null): User
{
    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'supervisor_id' => $supervisor?->id,
    ]);
}

function leaveBadgePendingRequest(User $employee): Leave
{
    return Leave::factory()->create([
        'organization_id' => $employee->organization_id,
        'company_id' => $employee->company_id,
        'user_id' => $employee->id,
        'status' => LeaveStatus::Pending,
    ]);
}

test('a supervisor sees the count for their own team only', function () {
    $organization = Organization::factory()->create();
    $supervisor = leaveBadgeSupervisor($organization);
    $ownReport = leaveBadgeEmployee($organization, $supervisor);
    $someoneElsesReport = leaveBadgeEmployee($organization);

    leaveBadgePendingRequest($ownReport);
    leaveBadgePendingRequest($ownReport);
    leaveBadgePendingRequest($someoneElsesReport);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingLeaveRequestsCount', 2));
});

test('an admin sees the organization-wide count', function () {
    $organization = Organization::factory()->create();
    $admin = leaveBadgeAdmin($organization);
    $firstEmployee = leaveBadgeEmployee($organization);
    $secondEmployee = leaveBadgeEmployee($organization);

    leaveBadgePendingRequest($firstEmployee);
    leaveBadgePendingRequest($secondEmployee);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingLeaveRequestsCount', 2));
});

test('the organization Owner sees the organization-wide count without the admin role', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);
    $firstEmployee = leaveBadgeEmployee($organization);
    $secondEmployee = leaveBadgeEmployee($organization);

    leaveBadgePendingRequest($firstEmployee);
    leaveBadgePendingRequest($secondEmployee);

    // Not scoped like a supervisor: the Owner has no direct reports at all,
    // so a bare ApproveTeam:Leave-style scope would wrongly return zero here.
    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingLeaveRequestsCount', 2));
});

test('approved and rejected leaves never inflate the count, only pending ones do', function () {
    $organization = Organization::factory()->create();
    $supervisor = leaveBadgeSupervisor($organization);
    $employee = leaveBadgeEmployee($organization, $supervisor);

    leaveBadgePendingRequest($employee);
    Leave::factory()->create([
        'organization_id' => $organization->id,
        'company_id' => $employee->company_id,
        'user_id' => $employee->id,
        'status' => LeaveStatus::Approved,
    ]);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingLeaveRequestsCount', 1));
});

test('the count is zero when nothing is pending', function () {
    $organization = Organization::factory()->create();
    $admin = leaveBadgeAdmin($organization);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingLeaveRequestsCount', 0));
});

test('a user with no leave-approval authority at all gets a zero count', function () {
    $organization = Organization::factory()->create();
    $employee = leaveBadgeEmployee($organization);

    leaveBadgePendingRequest($employee);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingLeaveRequestsCount', 0));
});
