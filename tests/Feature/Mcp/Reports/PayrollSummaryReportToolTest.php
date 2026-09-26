<?php

use App\Enums\WorkdayStatus;
use App\Mcp\Servers\KolviServer;
use App\Mcp\Tools\Reports\GetPayrollSummaryReportTool;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class)->group('mcp');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * A worked day: a mark (so the attendance/absence resolution counts it as
 * attended) plus a matching Workday row (so the hour totals sum something),
 * mirroring PayrollSummaryReportControllerTest's fixture.
 */
function payrollToolWorkedDay(Organization $organization, User $employee, string $date, string $workedTime = '08:00:00'): Workday
{
    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => "{$date} 08:00:00",
    ]);

    return Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'worked_time' => $workedTime,
        'missing_time' => '00:00:00',
        'in_time_difference' => '00:00:00',
        'status' => WorkdayStatus::Regular,
    ]);
}

test('an admin fetches a clean period\'s payroll summary as csv with no warning', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    payrollToolWorkedDay($organization, $employee, '2026-08-03', '07:15:00');

    KolviServer::actingAs($admin)
        ->tool(GetPayrollSummaryReportTool::class, [
            'period_year' => 2026,
            'period_month' => 8,
            'period_type' => 'first_fortnight',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('csv', fn (string $csv) => str_contains($csv, $employee->name) && str_contains($csv, '07:15:00'))
            ->missing('warning')
        );
});

test('an admin fetching a period with unresolved findings still gets the csv, plus a warning', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    $workday = payrollToolWorkedDay($organization, $employee, '2026-08-03');
    MarkModification::factory()->for($organization)->create([
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
    ]);

    KolviServer::actingAs($admin)
        ->tool(GetPayrollSummaryReportTool::class, [
            'period_year' => 2026,
            'period_month' => 8,
            'period_type' => 'first_fortnight',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('csv', fn (string $csv) => str_contains($csv, $employee->name))
            ->has('warning.findings', 1)
            ->where('warning.findings.0.employee_id', $employee->id)
            ->etc()
        );
});

test('the get-payroll-summary-report tool limits the report to the given employee_ids', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $included = User::factory()->for($organization)->employee()->create();
    $excluded = User::factory()->for($organization)->employee()->create();

    payrollToolWorkedDay($organization, $included, '2026-08-03');
    payrollToolWorkedDay($organization, $excluded, '2026-08-03');

    KolviServer::actingAs($admin)
        ->tool(GetPayrollSummaryReportTool::class, [
            'period_year' => 2026,
            'period_month' => 8,
            'period_type' => 'first_fortnight',
            'employee_ids' => [$included->id],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('csv', fn (string $csv) => str_contains($csv, $included->name) && ! str_contains($csv, $excluded->name))
        );
});

test('a call to the get-payroll-summary-report tool is recorded in the payroll export activity log', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    payrollToolWorkedDay($organization, $employee, '2026-08-03');

    KolviServer::actingAs($admin)
        ->tool(GetPayrollSummaryReportTool::class, [
            'period_year' => 2026,
            'period_month' => 8,
            'period_type' => 'first_fortnight',
        ])
        ->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'payroll_export')
        ->where('description', 'Exported payroll report')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['report_type'])->toBe('payroll-summary')
        ->and($activity->properties['format'])->toBe('csv')
        ->and($activity->properties['employee_ids'])->toContain($employee->id);
});

test('a user without Export:PayrollReport is denied by the get-payroll-summary-report tool', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    KolviServer::actingAs($user)
        ->tool(GetPayrollSummaryReportTool::class, [
            'period_year' => 2026,
            'period_month' => 8,
        ])
        ->assertHasErrors();
});
