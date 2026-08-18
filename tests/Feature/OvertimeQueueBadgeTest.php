<?php

use App\Enums\OvertimeAuthorizationMode;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The shared `auth.pendingOvertimeRequestsCount` nav badge (KOL-72): the
 * count of pending OvertimeRequest rows only, for the "Horas extra
 * pendientes" sidebar link into the standalone Solicitudes screen — scoped
 * exactly like OvertimeRequestController::index. Pending shift-excess
 * OvertimeAuthorization rows are a separate concern (decided on Jornadas,
 * KOL-71) and never inflate this count.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function badgeAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function badgeSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

function badgeEmployee(Organization $organization, ?User $supervisor = null): User
{
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'supervisor_id' => $supervisor?->id,
    ]);
    $employee->assignRole('employee');

    return $employee;
}

function badgeCombinedMode(Organization $organization): void
{
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined,
    ]);
}

function badgePendingRequest(User $employee): OvertimeRequest
{
    return OvertimeRequest::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
}

test('a supervisor sees the count for their own team only', function () {
    $organization = Organization::factory()->create();
    badgeCombinedMode($organization);
    $supervisor = badgeSupervisor($organization);
    $ownReport = badgeEmployee($organization, $supervisor);
    $someoneElsesReport = badgeEmployee($organization);

    badgePendingRequest($ownReport);
    badgePendingRequest($ownReport);
    badgePendingRequest($someoneElsesReport);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeRequestsCount', 2));
});

test('an admin sees the organization-wide count', function () {
    $organization = Organization::factory()->create();
    badgeCombinedMode($organization);
    $admin = badgeAdmin($organization);
    $firstEmployee = badgeEmployee($organization);
    $secondEmployee = badgeEmployee($organization);

    badgePendingRequest($firstEmployee);
    badgePendingRequest($secondEmployee);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeRequestsCount', 2));
});

test('pending shift-excess authorizations never inflate the count, only requests do', function () {
    $organization = Organization::factory()->create();
    badgeCombinedMode($organization);
    $supervisor = badgeSupervisor($organization);
    $employee = badgeEmployee($organization, $supervisor);

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);
    OvertimeAuthorization::openFor($workday);
    badgePendingRequest($employee);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeRequestsCount', 1));
});

test('the count is zero under pure post-hoc mode even if a request row exists', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PostHoc,
    ]);
    $supervisor = badgeSupervisor($organization);
    $employee = badgeEmployee($organization, $supervisor);

    badgePendingRequest($employee);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeRequestsCount', 0));
});

test('the count is zero when nothing is pending', function () {
    $organization = Organization::factory()->create();
    badgeCombinedMode($organization);
    $admin = badgeAdmin($organization);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeRequestsCount', 0));
});

test('a user with no overtime authority at all gets a zero count', function () {
    $organization = Organization::factory()->create();
    badgeCombinedMode($organization);
    $employee = badgeEmployee($organization);

    badgePendingRequest($employee);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeRequestsCount', 0));
});
