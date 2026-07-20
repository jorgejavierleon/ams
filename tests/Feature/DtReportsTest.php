<?php

use App\Models\Organization;
use App\Models\Position;
use App\Models\Premise;
use App\Models\User;
use Illuminate\Support\Carbon;

uses()->group('dt');

test('guests cannot access the reports filter', function () {
    $this->get(route('dt.reports.index'))
        ->assertRedirect(route('dt.login'));
});

test('dt users without a selected organization are redirected to the selector', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.reports.index'))
        ->assertRedirect(route('dt.organization.select'));
});

test('the filter page renders as the reports landing page', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/reports/index')
            ->where('reportType', null)
        );
});

test('the filter option lists are scoped to the audit session organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();
    $other = Organization::factory()->create();

    $employee = User::factory()->for($audited)->employee()->create();
    User::factory()->for($other)->employee()->create();

    $auditedPosition = Position::factory()->for($audited)->create();
    Position::factory()->for($other)->create();

    $auditedPremise = Premise::factory()->for($audited)->create();
    Premise::factory()->for($other)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('options.employees', 1)
            ->where('options.employees.0.value', (string) $employee->id)
            ->has('options.positions', 1)
            ->where('options.positions.0.value', (string) $auditedPosition->id)
            ->has('options.premises', 1)
            ->where('options.premises.0.value', (string) $auditedPremise->id)
        );
});

test('legal representatives are excluded from the employee options', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    User::factory()->for($organization)->employee()->create(['is_legal_rep' => true]);
    $employee = User::factory()->for($organization)->employee()->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('options.employees', 1)
            ->where('options.employees.0.value', (string) $employee->id)
        );
});

test('the date range defaults to the current month', function () {
    Carbon::setTestNow('2026-02-14 10:00:00');

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('filters.start', '2026-02-01')
            ->where('filters.end', '2026-02-28')
        );
});

test('the filter state is parsed from the query string', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $employee = User::factory()->for($organization)->employee()->create();
    $position = Position::factory()->for($organization)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-01-01',
            'end' => '2026-01-31',
            'employees' => [$employee->id],
            'positions' => [$position->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.start', '2026-01-01')
            ->where('filters.end', '2026-01-31')
            ->where('filters.employees', [$employee->id])
            ->where('filters.positions', [$position->id])
            ->where('filters.premises', [])
        );
});

test('each report route renders the filter page pre-selected on its type', function (string $routeName, string $reportType) {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/reports/index')
            ->where('reportType', $reportType)
        );
})->with([
    'attendance' => ['dt.reports.attendance', 'attendance'],
    'daily' => ['dt.reports.daily', 'daily'],
    'shift changes' => ['dt.reports.shift-changes', 'shift-changes'],
    'sundays' => ['dt.reports.sundays', 'sundays'],
    'incidents' => ['dt.reports.incidents', 'incidents'],
]);
