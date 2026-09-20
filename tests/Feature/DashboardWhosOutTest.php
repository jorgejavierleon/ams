<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The dashboard's "Who's out today" widget (KOL-119.3): DashboardController's
 * whosOut() is scoped exactly like LeavePolicy::viewTeam — null (widget
 * hidden) for anyone holding neither ViewTeam:Leave nor the admin role,
 * org-wide for admins, the supervisor's own direct reports otherwise.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function whosOutAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function whosOutSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

function whosOutEmployee(Organization $organization, ?User $supervisor = null): User
{
    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'supervisor_id' => $supervisor?->id,
    ]);
}

function whosOutApprovedLeave(User $employee, string $start, string $end): Leave
{
    return Leave::factory()->create([
        'organization_id' => $employee->organization_id,
        'company_id' => $employee->company_id,
        'user_id' => $employee->id,
        'status' => LeaveStatus::Approved,
        'type' => LeaveType::Vacation,
        'start_date' => $start,
        'end_date' => $end,
    ]);
}

test('a supervisor sees a direct report currently on leave with the return date', function () {
    $organization = Organization::factory()->create();
    $supervisor = whosOutSupervisor($organization);
    $report = whosOutEmployee($organization, $supervisor);

    whosOutApprovedLeave($report, now()->subDay()->toDateString(), now()->addDays(2)->toDateString());

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('whosOut', 1)
            ->where('whosOut.0.user.id', $report->id)
            ->where('whosOut.0.type', LeaveType::Vacation->value)
            ->where('whosOut.0.return_date', now()->addDays(3)->toDateString()));
});

test('a supervisor does not see another team\'s employee on leave', function () {
    $organization = Organization::factory()->create();
    $supervisor = whosOutSupervisor($organization);
    $someoneElsesReport = whosOutEmployee($organization);

    whosOutApprovedLeave($someoneElsesReport, now()->toDateString(), now()->toDateString());

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('whosOut', 0));
});

test('an admin sees who is out across the whole organization', function () {
    $organization = Organization::factory()->create();
    $admin = whosOutAdmin($organization);
    $first = whosOutEmployee($organization);
    $second = whosOutEmployee($organization);

    whosOutApprovedLeave($first, now()->toDateString(), now()->toDateString());
    whosOutApprovedLeave($second, now()->toDateString(), now()->toDateString());

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('whosOut', 2));
});

test('a leave that does not overlap today is excluded', function () {
    $organization = Organization::factory()->create();
    $supervisor = whosOutSupervisor($organization);
    $report = whosOutEmployee($organization, $supervisor);

    whosOutApprovedLeave($report, now()->addDays(3)->toDateString(), now()->addDays(5)->toDateString());
    whosOutApprovedLeave($report, now()->subDays(5)->toDateString(), now()->subDays(3)->toDateString());

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('whosOut', 0));
});

test('a pending leave overlapping today is excluded, only approved counts', function () {
    $organization = Organization::factory()->create();
    $supervisor = whosOutSupervisor($organization);
    $report = whosOutEmployee($organization, $supervisor);

    Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $report->id,
        'status' => LeaveStatus::Pending,
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('whosOut', 0));
});

test('the widget shows an explicit empty state when the viewer has visibility but nobody is out', function () {
    $organization = Organization::factory()->create();
    $supervisor = whosOutSupervisor($organization);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('whosOut', []));
});

test('the widget is hidden entirely for a user with no team-leave visibility', function () {
    $organization = Organization::factory()->create();
    $employee = whosOutEmployee($organization);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('whosOut', null));
});
