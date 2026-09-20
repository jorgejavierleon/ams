<?php

use App\Enums\WorkdayStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The dashboard's attendance-rate stat card (KOL-120): DashboardController's
 * attendanceRate() is scoped exactly like whosOut() — null (card hidden) for
 * anyone holding neither ViewTeam:Workday nor the admin role, org-wide for
 * admins, the supervisor's own direct reports otherwise.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function attendanceRateAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function attendanceRateSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

function attendanceRateEmployee(Organization $organization, ?User $supervisor = null): User
{
    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'supervisor_id' => $supervisor?->id,
    ]);
}

function attendanceRateWorkday(User $employee, string $date, WorkdayStatus $status): Workday
{
    return Workday::factory()
        ->when($status === WorkdayStatus::Absent, fn ($factory) => $factory->absent())
        ->create([
            'organization_id' => $employee->organization_id,
            'user_id' => $employee->id,
            'date' => $date,
            'status' => $status,
        ]);
}

test('the card is hidden entirely for a user with no team-workday visibility', function () {
    $organization = Organization::factory()->create();
    $employee = attendanceRateEmployee($organization);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attendanceRate', null));
});

test('a supervisor sees the attendance rate for their own team this week', function () {
    $organization = Organization::factory()->create();
    $supervisor = attendanceRateSupervisor($organization);
    $report = attendanceRateEmployee($organization, $supervisor);
    $someoneElsesReport = attendanceRateEmployee($organization);

    $monday = Carbon::today()->startOfWeek(Carbon::MONDAY);

    attendanceRateWorkday($report, $monday->toDateString(), WorkdayStatus::Regular);
    attendanceRateWorkday($report, $monday->copy()->addDay()->toDateString(), WorkdayStatus::Absent);
    // Not on this supervisor's team: must not affect the rate.
    attendanceRateWorkday($someoneElsesReport, $monday->toDateString(), WorkdayStatus::Absent);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attendanceRate.rate', fn ($rate) => (float) $rate === 50.0));
});

test('an admin sees the attendance rate across the whole organization', function () {
    $organization = Organization::factory()->create();
    $admin = attendanceRateAdmin($organization);
    $first = attendanceRateEmployee($organization);
    $second = attendanceRateEmployee($organization);

    $monday = Carbon::today()->startOfWeek(Carbon::MONDAY);

    attendanceRateWorkday($first, $monday->toDateString(), WorkdayStatus::Regular);
    attendanceRateWorkday($second, $monday->toDateString(), WorkdayStatus::Regular);
    attendanceRateWorkday($second, $monday->copy()->addDay()->toDateString(), WorkdayStatus::Absent);

    $expected = round(2 / 3 * 100, 1);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attendanceRate.rate', fn ($rate) => (float) $rate === $expected));
});

test('the trend compares this week to the prior week', function () {
    $organization = Organization::factory()->create();
    $supervisor = attendanceRateSupervisor($organization);
    $report = attendanceRateEmployee($organization, $supervisor);

    $monday = Carbon::today()->startOfWeek(Carbon::MONDAY);
    $lastMonday = $monday->copy()->subWeek();

    // This week: 100% attended.
    attendanceRateWorkday($report, $monday->toDateString(), WorkdayStatus::Regular);
    // Last week: 50% attended.
    attendanceRateWorkday($report, $lastMonday->toDateString(), WorkdayStatus::Regular);
    attendanceRateWorkday($report, $lastMonday->copy()->addDay()->toDateString(), WorkdayStatus::Absent);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendanceRate.rate', fn ($rate) => (float) $rate === 100.0)
            ->where('attendanceRate.trend', fn ($trend) => (float) $trend === 50.0));
});

test('the card shows an explicit empty state when there are no scheduled workdays this week', function () {
    $organization = Organization::factory()->create();
    $supervisor = attendanceRateSupervisor($organization);
    attendanceRateEmployee($organization, $supervisor);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendanceRate.rate', null)
            ->where('attendanceRate.trend', null));
});
