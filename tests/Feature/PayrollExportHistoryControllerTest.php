<?php

use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function historyAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function historyPeriodMovementsExportUrl(array $query = []): string
{
    return route('payroll-reports.period-movements.export', [
        'format' => 'excel',
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'month',
        ...$query,
    ]);
}

function historySummaryExportUrl(array $query = []): string
{
    return route('payroll-reports.summary.export', [
        'format' => 'pdf',
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'first_fortnight',
        ...$query,
    ]);
}

test('an export is recorded with its report type, period, format and the employees it covered, visible in the history', function () {
    $admin = historyAdmin();
    $employee = User::factory()->for($admin->organization)->employee()->create();

    $this->actingAs($admin)
        ->get(historyPeriodMovementsExportUrl(['selectAll' => 1]))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('payroll-reports.history'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('payroll-reports/history')
                ->has('exports.data', 1)
                ->where('exports.data.0.report_type', 'period-movements')
                ->where('exports.data.0.format', 'excel')
                ->where('exports.data.0.employee_count', 1)
                ->where('exports.data.0.warned', false)
                ->has('exports.data.0.employees', 1)
                ->where('exports.data.0.employees.0.name', $employee->name)
                ->has('exports.data.0.causer.name'),
        );
});

test('an export that covered no employees is recorded with a null employees list', function () {
    $admin = historyAdmin();

    $this->actingAs($admin)
        ->get(historyPeriodMovementsExportUrl(['selectAll' => 0]))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('payroll-reports.history'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('exports.data', 1)
                ->where('exports.data.0.employee_count', 0)
                ->where('exports.data.0.employees', null),
        );
});

test('an export that proceeded past an integrity warning is recorded with the confirmation and the unresolved finding types', function () {
    $admin = historyAdmin();
    $employee = User::factory()->for($admin->organization)->employee()->create();

    $workday = Workday::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'date' => '2026-08-03',
    ]);
    MarkModification::factory()->for($admin->organization)->create([
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($admin)
        ->get(historySummaryExportUrl(['selectAll' => 1, 'confirmed' => 1]))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('payroll-reports.history'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('exports.data', 1)
                ->where('exports.data.0.warned', true)
                ->where('exports.data.0.confirmed', true)
                ->where(
                    'exports.data.0.finding_types',
                    fn (Collection $types) => $types->contains('pending_mark_modification'),
                ),
        );
});

test('the export history is organization-scoped: one tenant never sees another tenant\'s exports', function () {
    $adminA = historyAdmin();
    $adminB = historyAdmin();
    User::factory()->for($adminA->organization)->employee()->create();
    User::factory()->for($adminB->organization)->employee()->create();

    $this->actingAs($adminA)->get(historyPeriodMovementsExportUrl(['selectAll' => 1]))->assertOk();
    $this->actingAs($adminB)->get(historyPeriodMovementsExportUrl(['selectAll' => 1]))->assertOk();

    $this->actingAs($adminA)
        ->get(route('payroll-reports.history'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('exports.data', 1)
                ->where('exports.data.0.causer.name', $adminA->name),
        );
});

test('a tenant admin can view the export history without superadmin access', function () {
    $admin = historyAdmin();

    $this->actingAs($admin)
        ->get(route('payroll-reports.history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('payroll-reports/history'));
});

test('a user without View:PayrollReport is forbidden from the export history', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('payroll-reports.history'))
        ->assertForbidden();
});

test('the export history can be filtered by report type', function () {
    $admin = historyAdmin();
    User::factory()->for($admin->organization)->employee()->create();

    $this->actingAs($admin)->get(historyPeriodMovementsExportUrl(['selectAll' => 1]))->assertOk();
    $this->actingAs($admin)->get(historySummaryExportUrl(['selectAll' => 1]))->assertOk();

    $this->actingAs($admin)
        ->get(route('payroll-reports.history', ['report_type' => 'payroll-summary']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('exports.data', 1)
                ->where('exports.data.0.report_type', 'payroll-summary'),
        );
});
