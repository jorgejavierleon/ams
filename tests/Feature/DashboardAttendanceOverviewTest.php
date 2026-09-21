<?php

use App\Enums\WorkdayStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The dashboard's attendance overview chart (KOL-121): DashboardController's
 * attendanceOverview() is scoped exactly like attendanceRate() — null (chart
 * hidden) for anyone holding neither ViewTeam:Workday nor the admin role,
 * org-wide for admins, the supervisor's own direct reports otherwise.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function overviewAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function overviewSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

function overviewEmployee(Organization $organization, ?User $supervisor = null): User
{
    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'supervisor_id' => $supervisor?->id,
    ]);
}

/**
 * @return array<int, array{date: string, on_time: int, late: int, absent: int}>
 */
function overviewDays(Assert $page): array
{
    return Arr::get($page->toArray(), 'props.attendanceOverview.days');
}

function overviewDay(Assert $page, string $date): ?array
{
    return collect(overviewDays($page))->firstWhere('date', $date);
}

test('the chart is hidden entirely for a user with no team-workday visibility', function () {
    $organization = Organization::factory()->create();
    $employee = overviewEmployee($organization);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attendanceOverview', null));
});

test('a supervisor sees only their own team\'s daily counts', function () {
    $organization = Organization::factory()->create();
    $supervisor = overviewSupervisor($organization);
    $report = overviewEmployee($organization, $supervisor);
    $someoneElsesReport = overviewEmployee($organization);

    $today = Carbon::today()->toDateString();

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $report->id,
        'date' => $today,
        'status' => WorkdayStatus::Regular,
        'in_time_difference' => '00:00:00',
    ]);
    // Not on this supervisor's team: must not affect the counts.
    Workday::factory()->absent()->create([
        'organization_id' => $organization->id,
        'user_id' => $someoneElsesReport->id,
        'date' => $today,
    ]);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) use ($today) {
            $page->has('attendanceOverview.days', 28);

            expect(overviewDay($page, $today))
                ->toBe(['date' => $today, 'on_time' => 1, 'late' => 0, 'absent' => 0]);
        });
});

test('an admin sees the overview across the whole organization', function () {
    $organization = Organization::factory()->create();
    $admin = overviewAdmin($organization);
    $first = overviewEmployee($organization);
    $second = overviewEmployee($organization);

    $today = Carbon::today()->toDateString();

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $first->id,
        'date' => $today,
        'status' => WorkdayStatus::Regular,
        'in_time_difference' => '00:00:00',
    ]);
    Workday::factory()->absent()->create([
        'organization_id' => $organization->id,
        'user_id' => $second->id,
        'date' => $today,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) use ($today) {
            expect(overviewDay($page, $today))
                ->toBe(['date' => $today, 'on_time' => 1, 'late' => 0, 'absent' => 1]);
        });
});

test('a day counts as late when in_time_difference is positive and on-time otherwise', function () {
    $organization = Organization::factory()->create();
    $supervisor = overviewSupervisor($organization);
    $onTimeReport = overviewEmployee($organization, $supervisor);
    $lateReport = overviewEmployee($organization, $supervisor);
    $earlyReport = overviewEmployee($organization, $supervisor);

    $today = Carbon::today()->toDateString();

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $onTimeReport->id,
        'date' => $today,
        'status' => WorkdayStatus::Regular,
        'in_time_difference' => '00:00:00',
    ]);
    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $lateReport->id,
        'date' => $today,
        'status' => WorkdayStatus::Irregular,
        'in_time_difference' => '00:05:00',
    ]);
    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $earlyReport->id,
        'date' => $today,
        'status' => WorkdayStatus::Regular,
        'in_time_difference' => '-00:03:00',
    ]);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) use ($today) {
            expect(overviewDay($page, $today))
                ->toBe(['date' => $today, 'on_time' => 2, 'late' => 1, 'absent' => 0]);
        });
});

test('a day counts as absent from WorkdayStatus::Absent regardless of the split', function () {
    $organization = Organization::factory()->create();
    $supervisor = overviewSupervisor($organization);
    $report = overviewEmployee($organization, $supervisor);

    $today = Carbon::today()->toDateString();

    Workday::factory()->absent()->create([
        'organization_id' => $organization->id,
        'user_id' => $report->id,
        'date' => $today,
    ]);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) use ($today) {
            expect(overviewDay($page, $today))
                ->toBe(['date' => $today, 'on_time' => 0, 'late' => 0, 'absent' => 1]);
        });
});

test('the chart covers all 28 days in the period, zero-filled where there is no data', function () {
    $organization = Organization::factory()->create();
    $supervisor = overviewSupervisor($organization);
    $report = overviewEmployee($organization, $supervisor);

    $periodStart = Carbon::today()->subDays(27)->toDateString();

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $report->id,
        'date' => $periodStart,
        'status' => WorkdayStatus::Regular,
        'in_time_difference' => '00:00:00',
    ]);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) use ($periodStart) {
            $days = overviewDays($page);

            expect($days)->toHaveCount(28)
                ->and(overviewDay($page, $periodStart))
                ->toBe(['date' => $periodStart, 'on_time' => 1, 'late' => 0, 'absent' => 0])
                ->and(collect($days)->sum('on_time'))->toBe(1);
        });
});

test('the chart shows an explicit empty state when there are no workdays in the period', function () {
    $organization = Organization::factory()->create();
    $supervisor = overviewSupervisor($organization);
    overviewEmployee($organization, $supervisor);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attendanceOverview.days', []));
});
