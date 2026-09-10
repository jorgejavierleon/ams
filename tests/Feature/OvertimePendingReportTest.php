<?php

use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workday;
use App\Services\Overtime\OvertimePendingReport;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * KOL-52, PRD §12: the stale-overtime report. KOL-80 made "pending" a
 * read-only condition (no OvertimeAuthorization row, or one left undecided)
 * rather than a persisted queue state, so these tests build stale days
 * directly on Workday rather than seeding OvertimeAuthorization::Pending rows.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function pendingReportAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function pendingReportEmployee(Organization $organization, ?User $supervisor = null): User
{
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'supervisor_id' => $supervisor?->id,
    ]);
    $employee->assignRole('employee');

    return $employee;
}

/**
 * A day with calculated overtime and no approved decision — the report's raw
 * material — dated a fixed number of days in the past.
 */
function pendingReportStaleWorkday(User $employee, int $daysAgo, string $calculatedOvertime = '01:00:00'): Workday
{
    return Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->subDays($daysAgo),
        'calculated_overtime' => $calculatedOvertime,
    ]);
}

// --- Access control ---

test('a user without Manage:OvertimeAuthorization cannot reach the report', function () {
    $organization = Organization::factory()->create();
    $employee = pendingReportEmployee($organization);

    $this->actingAs($employee)->get(route('overtime.pending.index'))->assertForbidden();
});

// --- The threshold boundary ---

test('a day within the configured threshold is not listed', function () {
    $admin = pendingReportAdmin();
    $employee = pendingReportEmployee($admin->organization);
    pendingReportStaleWorkday($employee, daysAgo: 5);

    Setting::factory()->create([
        'organization_id' => $admin->organization_id,
        'overtime_pending_alert_threshold_days' => 15,
    ]);

    $this->actingAs($admin)
        ->get(route('overtime.pending.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('records.data', [])
            ->where('stats.stale_count', 0)
        );
});

test('a day beyond the configured threshold is listed with its employee, supervisor and days pending', function () {
    $admin = pendingReportAdmin();
    $supervisor = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $supervisor->assignRole('supervisor');
    $employee = pendingReportEmployee($admin->organization, $supervisor);
    pendingReportStaleWorkday($employee, daysAgo: 20, calculatedOvertime: '02:00:00');

    Setting::factory()->create([
        'organization_id' => $admin->organization_id,
        'overtime_pending_alert_threshold_days' => 15,
    ]);

    $this->actingAs($admin)
        ->get(route('overtime.pending.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.stale_count', 1)
            ->where('stats.threshold_days', 15)
            ->where('records.data.0.employee', $employee->name)
            ->where('records.data.0.supervisor', $supervisor->name)
            ->where('records.data.0.days_pending', 20)
            ->where('records.data.0.calculated_hours', '02:00:00')
        );
});

test('a day with nothing calculated is never listed', function () {
    $admin = pendingReportAdmin();
    $employee = pendingReportEmployee($admin->organization);
    Workday::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'date' => now()->subDays(60),
        'calculated_overtime' => '00:00:00',
    ]);

    expect(app(OvertimePendingReport::class)->staleCount(15))->toBe(0);
});

// --- Approval and revocation ---

test('an approved day drops out of the report even if it is old', function () {
    $admin = pendingReportAdmin();
    $employee = pendingReportEmployee($admin->organization);
    $workday = pendingReportStaleWorkday($employee, daysAgo: 40);

    OvertimeAuthorization::factory()->approved($admin)->create([
        'organization_id' => $admin->organization_id,
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
        'date' => $workday->date,
        'calculated_hours' => $workday->calculated_overtime,
        'reason' => 'Autorización de prueba.',
    ]);

    Setting::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('overtime.pending.index'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.stale_count', 0));
});

test('a revoked day is stale again once it is old enough', function () {
    $admin = pendingReportAdmin();
    $employee = pendingReportEmployee($admin->organization);
    $workday = pendingReportStaleWorkday($employee, daysAgo: 40);

    OvertimeAuthorization::factory()->revoked($admin)->create([
        'organization_id' => $admin->organization_id,
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
        'date' => $workday->date,
        'calculated_hours' => $workday->calculated_overtime,
    ]);

    Setting::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('overtime.pending.index'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.stale_count', 1));
});

test('the stale-count stat agrees with a search filter rather than showing the unfiltered total', function () {
    $admin = pendingReportAdmin();
    $matching = pendingReportEmployee($admin->organization);
    $matching->update(['name' => 'Zzyzx Match']);
    $other = pendingReportEmployee($admin->organization);
    pendingReportStaleWorkday($matching, daysAgo: 20);
    pendingReportStaleWorkday($other, daysAgo: 20);

    Setting::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('overtime.pending.index', ['search' => 'Zzyzx']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.stale_count', 1)
            ->has('records.data', 1));
});

// --- Organization scoping ---

test('a stale day from another organization never appears', function () {
    $admin = pendingReportAdmin();
    $otherOrg = Organization::factory()->create();
    $otherEmployee = pendingReportEmployee($otherOrg);
    pendingReportStaleWorkday($otherEmployee, daysAgo: 60);

    $this->actingAs($admin)
        ->get(route('overtime.pending.index'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.stale_count', 0));
});

// --- The resolution-time metric (AC #4) ---

test('the average resolution time is computed from approved records', function () {
    $admin = pendingReportAdmin();
    $employeeA = pendingReportEmployee($admin->organization);
    $employeeB = pendingReportEmployee($admin->organization);

    // Decided 4 days after the marked day.
    OvertimeAuthorization::factory()->approved($admin)->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employeeA->id,
        'date' => now()->subDays(10),
        'reviewed_at' => now()->subDays(6),
        'reason' => 'Autorización de prueba.',
    ]);

    // Decided 10 days after the marked day.
    OvertimeAuthorization::factory()->approved($admin)->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employeeB->id,
        'date' => now()->subDays(20),
        'reviewed_at' => now()->subDays(10),
        'reason' => 'Autorización de prueba.',
    ]);

    Setting::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('overtime.pending.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.average_resolution_days', fn ($average) => (float) $average === 7.0)
        );
});

test('the average resolution time is null when nothing has ever been approved', function () {
    $admin = pendingReportAdmin();
    Setting::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('overtime.pending.index'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.average_resolution_days', null));
});

// --- Bounded query count (AC #6) ---

test('the report stays bounded in query count for many stale records', function () {
    $admin = pendingReportAdmin();
    $employee = pendingReportEmployee($admin->organization);

    foreach (range(1, 25) as $i) {
        pendingReportStaleWorkday($employee, daysAgo: 20 + $i);
    }

    $this->actingAs($admin);

    DB::enableQueryLog();
    $this->get(route('overtime.pending.index'))->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(15);
});
