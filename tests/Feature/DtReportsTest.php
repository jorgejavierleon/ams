<?php

use App\Enums\LeaveType;
use App\Events\WorkdaysRecalculationNeeded;
use App\Models\Leave;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Premise;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

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
    'daily' => ['dt.reports.daily', 'daily'],
    'shift changes' => ['dt.reports.shift-changes', 'shift-changes'],
    'sundays' => ['dt.reports.sundays', 'sundays'],
    'incidents' => ['dt.reports.incidents', 'incidents'],
]);

test('the attendance report builds a per-worker daily grid marking attendance', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create(['name' => 'Ana']);

    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => '2026-03-03 08:00:00',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-04',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/reports/attendance')
            ->where('reportType', 'attendance')
            ->has('report', 1)
            ->where('report.0.employee', fn (string $value) => str_contains($value, 'Ana'))
            ->has('report.0.rows', 3)
            ->where('report.0.rows.0.date', '02/03/26')
            ->where('report.0.rows.0.attendance', false)
            ->where('report.0.rows.0.absence', 'unjustified')
            ->where('report.0.rows.1.date', '03/03/26')
            ->where('report.0.rows.1.attendance', true)
            ->where('report.0.rows.1.absence', null)
            ->where('report.0.rows.2.attendance', false)
        );
});

test('an approved leave marks the day justified with the leave type as observation', function () {
    Mail::fake();
    Event::fake([WorkdaysRecalculationNeeded::class]);

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create();

    Leave::factory()->for($organization)->approved()->create([
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => '2026-03-02',
        'end_date' => '2026-03-02',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.0.rows.0.attendance', false)
            ->where('report.0.rows.0.absence', 'justified')
            ->where('report.0.rows.0.observation.kind', 'leave')
            ->where('report.0.rows.0.observation.type', 'vacation_lead')
        );
});

test('a free shift day is justified and shown as a day off', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create();

    // The shift factory seeds one day per weekday; mark the report day's as free.
    $shift = Shift::factory()->for($organization)->create();
    $shift->days()
        ->where('weekday', Carbon::parse('2026-03-02')->dayOfWeekIso - 1)
        ->update(['is_free' => true]);
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.0.rows.0.absence', 'justified')
            ->where('report.0.rows.0.observation.kind', 'free')
        );
});

test('the report covers only the selected worker of the audit organization', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    $wanted = User::factory()->for($organization)->employee()->create(['name' => 'Wanted']);
    User::factory()->for($organization)->employee()->create(['name' => 'Ignored']);
    User::factory()->for($other)->employee()->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'employees' => [$wanted->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report', 1)
            ->where('report.0.employee', fn (string $value) => str_contains($value, 'Wanted'))
        );
});

test('a filter matching no workers returns an empty report', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $emptyPosition = Position::factory()->for($organization)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'positions' => [$emptyPosition->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('report', 0));
});
