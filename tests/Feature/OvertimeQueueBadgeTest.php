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
 * The shared `auth.pendingOvertimeCount` nav badge (KOL-66): the combined
 * count of pending OvertimeAuthorization rows and pending OvertimeRequest
 * rows, scoped exactly like the queue itself.
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

function badgePendingAuthorization(User $employee, ?string $date = null): OvertimeAuthorization
{
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => $date ?? now()->toDateString(),
    ]);

    return OvertimeAuthorization::openFor($workday);
}

test('a supervisor sees the count for their own team only', function () {
    $organization = Organization::factory()->create();
    $supervisor = badgeSupervisor($organization);
    $ownReport = badgeEmployee($organization, $supervisor);
    $someoneElsesReport = badgeEmployee($organization);

    badgePendingAuthorization($ownReport, '2026-08-01');
    badgePendingAuthorization($ownReport, '2026-08-02');
    badgePendingAuthorization($someoneElsesReport);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeCount', 2));
});

test('an admin sees the organization-wide count', function () {
    $organization = Organization::factory()->create();
    $admin = badgeAdmin($organization);
    $firstEmployee = badgeEmployee($organization);
    $secondEmployee = badgeEmployee($organization);

    badgePendingAuthorization($firstEmployee);
    badgePendingAuthorization($secondEmployee);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeCount', 2));
});

test('the count combines pending requests with pending shift-excess authorizations under combined mode', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined,
    ]);
    $supervisor = badgeSupervisor($organization);
    $employee = badgeEmployee($organization, $supervisor);

    badgePendingAuthorization($employee);
    OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeCount', 2));
});

test('pending requests are excluded from the count under pure post-hoc mode', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PostHoc,
    ]);
    $supervisor = badgeSupervisor($organization);
    $employee = badgeEmployee($organization, $supervisor);

    OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeCount', 0));
});

test('the count is zero when nothing is pending', function () {
    $organization = Organization::factory()->create();
    $admin = badgeAdmin($organization);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeCount', 0));
});

test('a user with no overtime authority at all gets a zero count', function () {
    $organization = Organization::factory()->create();
    $employee = badgeEmployee($organization);

    badgePendingAuthorization($employee);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.pendingOvertimeCount', 0));
});
