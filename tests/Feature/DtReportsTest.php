<?php

use App\Enums\LeaveType;
use App\Enums\MarkType;
use App\Enums\ShiftType;
use App\Events\WorkdaysRecalculationNeeded;
use App\Models\Holiday;
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

test('the daily report grid computes shortfall, overtime and signed weekly totals', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create(['name' => 'Ana']);

    // Auto-seeded days: Mon–Fri 08:00–17:00 (lunch 12:00–13:00, 8h), Sat/Sun free.
    $shift = Shift::factory()->for($organization)->create();
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);

    // Monday 2026-03-02: in 15 min late, out 30 min after the ordinary journey.
    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'type' => MarkType::In,
        'date_time' => '2026-03-02 08:15:00',
    ]);
    Mark::factory()->for($organization)->out()->create([
        'user_id' => $employee->id,
        'date_time' => '2026-03-02 17:30:00',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.daily', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/reports/daily')
            ->where('reportType', 'daily')
            ->has('report', 1)
            ->where('report.0.employee', fn (string $value) => str_contains($value, 'Ana'))
            ->where('report.0.hasFlexibleBand', false)
            ->where('report.0.exceptionalCycle', null)
            // The requested Monday expands to a full ISO week (Mon–Sun).
            ->has('report.0.weeks', 1)
            ->has('report.0.weeks.0.days', 7)
            ->where('report.0.weeks.0.days.0.date', '02/03/26')
            ->where('report.0.weeks.0.days.0.journey.start', '08:00:00')
            ->where('report.0.weeks.0.days.0.journey.end', '17:00:00')
            ->where('report.0.weeks.0.days.0.journeyMarks.in', '08:15:00')
            ->where('report.0.weeks.0.days.0.journeyMarks.out', '17:30:00')
            ->where('report.0.weeks.0.days.0.lunch.start', '12:00:00')
            ->where('report.0.weeks.0.days.0.lunchMarks', null)
            ->where('report.0.weeks.0.days.0.undertime', '-00:15:00')
            ->where('report.0.weeks.0.days.0.overtime', '+00:30:00')
            // Saturday is a free day: no journey, zeroed shortfall/overtime.
            ->where('report.0.weeks.0.days.5.journey', null)
            ->where('report.0.weeks.0.days.5.undertime', '00:00:00')
            // Weekly totals: 5 working days × 8h pacted journey.
            ->where('report.0.weeks.0.totals.journey', '+40:00:00')
            ->where('report.0.weeks.0.totals.overtime', '+00:30:00')
        );
});

test('an approved leave zeroes the day shortfall and shows the leave observation', function () {
    Mail::fake();
    Event::fake([WorkdaysRecalculationNeeded::class]);

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create();

    $shift = Shift::factory()->for($organization)->create();
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);

    Leave::factory()->for($organization)->approved()->create([
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => '2026-03-02',
        'end_date' => '2026-03-02',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.daily', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.0.weeks.0.days.0.observation.kind', 'leave')
            ->where('report.0.weeks.0.days.0.observation.type', 'vacation_lead')
            ->where('report.0.weeks.0.days.0.undertime', '00:00:00')
            ->where('report.0.weeks.0.days.0.overtime', '00:00:00')
        );
});

test('an exceptional shift adds the distribution cycle to the worker block', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create();

    $shift = Shift::factory()->for($organization)->create([
        'type' => ShiftType::Exceptional,
        'description' => 'Ciclo 4x4',
    ]);
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.daily', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.0.exceptionalCycle', 'Ciclo 4x4')
        );
});

test('the daily report is empty when the filter matches no workers', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $emptyPosition = Position::factory()->for($organization)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.daily', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'positions' => [$emptyPosition->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('report', 0));
});

test('the shift changes report lists each change with its previous and new shift', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create(['name' => 'Ana']);

    $firstShift = Shift::factory()->for($organization)->create([
        'type' => ShiftType::Fixed,
        'description' => 'de 08:00 a 17:00',
    ]);
    $secondShift = Shift::factory()->for($organization)->create([
        'type' => ShiftType::Rotational,
        'description' => 'de 09:00 a 18:00',
    ]);

    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $firstShift->id,
        'notification_date' => '2026-05-18',
        'start_date' => '2026-05-20',
        'end_date' => '2026-05-26',
        'requested_by_employee' => false,
    ]);
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $secondShift->id,
        'notification_date' => '2026-05-19',
        'start_date' => '2026-05-27',
        'end_date' => null,
        'requested_by_employee' => true,
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.shift-changes', [
            'start' => '2026-05-01',
            'end' => '2026-06-30',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/reports/shift-changes')
            ->where('reportType', 'shift-changes')
            ->has('report', 1)
            ->where('report.0.employee', fn (string $value) => str_contains($value, 'Ana'))
            ->where('report.0.emptyReason', null)
            ->has('report.0.rows', 2)
            // First change: no previous shift (Art. 27 d.2–d.4 absent).
            ->where('report.0.rows.0.oldStartDate', null)
            ->where('report.0.rows.0.oldShift', null)
            ->where('report.0.rows.0.oldExtension', null)
            ->where('report.0.rows.0.notificationDate', '18/05/26')
            ->where('report.0.rows.0.newStartDate', '20/05/26')
            ->where('report.0.rows.0.newShift', 'de 08:00 a 17:00')
            ->where('report.0.rows.0.newExtension', 'fixed')
            ->where('report.0.rows.0.requestedBy', 'employer')
            // Second change: previous shift carried from the first row.
            ->where('report.0.rows.1.oldStartDate', '20/05/26')
            ->where('report.0.rows.1.oldShift', 'de 08:00 a 17:00')
            ->where('report.0.rows.1.oldExtension', 'fixed')
            ->where('report.0.rows.1.notificationDate', '19/05/26')
            ->where('report.0.rows.1.newStartDate', '27/05/26')
            ->where('report.0.rows.1.newShift', 'de 09:00 a 18:00')
            ->where('report.0.rows.1.newExtension', 'rotational')
            ->where('report.0.rows.1.requestedBy', 'employee')
        );
});

test('a worker on a fixed permanent journey with no change gets the fixed-journey legend', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create();

    $shift = Shift::factory()->for($organization)->create(['type' => ShiftType::Fixed]);
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => '2020-01-01',
        'end_date' => null,
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.shift-changes', [
            'start' => '2026-05-01',
            'end' => '2026-05-31',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report', 1)
            ->has('report.0.rows', 0)
            ->where('report.0.emptyReason', 'fixed-journey')
        );
});

test('a shift worker with no change in the range gets the no-changes legend', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create();

    $shift = Shift::factory()->for($organization)->create(['type' => ShiftType::Rotational]);
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => '2020-01-01',
        'end_date' => null,
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.shift-changes', [
            'start' => '2026-05-01',
            'end' => '2026-05-31',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report.0.rows', 0)
            ->where('report.0.emptyReason', 'no-changes')
        );
});

test('the shift changes report is empty when the filter matches no workers', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $emptyPosition = Position::factory()->for($organization)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.shift-changes', [
            'start' => '2026-05-01',
            'end' => '2026-05-31',
            'positions' => [$emptyPosition->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('report', 0));
});

test('the sundays report lists worked Sundays and holidays with monthly and total subtotals', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create(['name' => 'Ana']);

    // 2026-03-06 is a Friday holiday, 2026-03-08 is a Sunday — both worked.
    Holiday::factory()->create(['date' => '2026-03-06', 'name' => 'Día de Prueba']);
    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => '2026-03-06 09:00:00',
    ]);
    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => '2026-03-08 09:00:00',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.sundays', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/reports/sundays')
            ->where('reportType', 'sundays')
            ->has('report', 1)
            ->where('report.0.employee', fn (string $value) => str_contains($value, 'Ana'))
            ->where('report.0.additionalSundays', false)
            ->where('report.0.emptyReason', null)
            ->where('report.0.total', 2)
            ->has('report.0.months', 1)
            ->where('report.0.months.0.worked', 2)
            ->has('report.0.months.0.rows', 2)
            // Rows are chronological: the Friday holiday, then the Sunday.
            ->where('report.0.months.0.rows.0.date', '06/03/26')
            ->where('report.0.months.0.rows.0.dayType', 'holiday')
            ->where('report.0.months.0.rows.0.holiday', 'Día de Prueba')
            ->where('report.0.months.0.rows.0.attendance', true)
            ->where('report.0.months.0.rows.0.absence', null)
            ->where('report.0.months.0.rows.1.date', '08/03/26')
            ->where('report.0.months.0.rows.1.dayType', 'sunday')
            ->where('report.0.months.0.rows.1.attendance', true)
        );
});

test('a worker rostered on a Sunday who did not attend is marked justified by leave', function () {
    Mail::fake();
    Event::fake([WorkdaysRecalculationNeeded::class]);

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create([
        'has_additional_sundays' => true,
    ]);

    // Roster the worker on Sundays (weekday 6) and cover the day with leave.
    $shift = Shift::factory()->for($organization)->create();
    $shift->days()->where('weekday', 6)->update(['is_free' => false]);
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);
    Leave::factory()->for($organization)->approved()->create([
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => '2026-03-08',
        'end_date' => '2026-03-08',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.sundays', [
            'start' => '2026-03-08',
            'end' => '2026-03-08',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.0.additionalSundays', true)
            ->where('report.0.emptyReason', null)
            ->where('report.0.total', 0)
            ->has('report.0.months.0.rows', 1)
            ->where('report.0.months.0.rows.0.attendance', false)
            ->where('report.0.months.0.rows.0.absence', 'justified')
            ->where('report.0.months.0.rows.0.observation.kind', 'leave')
            ->where('report.0.months.0.rows.0.observation.type', 'vacation_lead')
        );
});

test('a Monday-to-Friday worker gets the fixed-journey legend', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $employee = User::factory()->for($organization)->employee()->create();

    // Default shift: Mon–Fri working, Sat/Sun free. No Sunday/holiday marks.
    $shift = Shift::factory()->for($organization)->create();
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.sundays', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report', 1)
            ->has('report.0.months', 0)
            ->where('report.0.total', 0)
            ->where('report.0.emptyReason', 'no-sundays')
        );
});

test('the sundays report is empty when the filter matches no workers', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $emptyPosition = Position::factory()->for($organization)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.sundays', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
            'positions' => [$emptyPosition->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('report', 0));
});

test('the filter options expose the shift types and shifts labelled by extension', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    // The observer seeds Mon–Fri working (08:00–17:00), Sat/Sun free.
    Shift::factory()->for($organization)->create(['name' => 'Turno mañana']);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('options.journals', count(ShiftType::cases()))
            ->where('options.journals.0.value', ShiftType::Fixed->value)
            ->has('options.shifts', 1)
            ->where('options.shifts.0.label', 'Lunes a Viernes 08:00–17:00')
        );
});

test('the shifts options are scoped to the audit session organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();
    $other = Organization::factory()->create();

    $auditedShift = Shift::factory()->for($audited)->create();
    Shift::factory()->for($other)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('options.shifts', 1)
            ->where('options.shifts.0.value', (string) $auditedShift->id)
        );
});

test('the jornada filter narrows the report to workers on a matching shift type', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $fixedWorker = User::factory()->for($organization)->employee()->create(['name' => 'Fija']);
    $rotationalWorker = User::factory()->for($organization)->employee()->create(['name' => 'Rotativa']);

    $fixedShift = Shift::factory()->for($organization)->create(['type' => ShiftType::Fixed]);
    $rotationalShift = Shift::factory()->for($organization)->create(['type' => ShiftType::Rotational]);

    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $fixedWorker->id,
        'shift_id' => $fixedShift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $rotationalWorker->id,
        'shift_id' => $rotationalShift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'journals' => [ShiftType::Rotational->value],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report', 1)
            ->where('report.0.employee', fn (string $value) => str_contains($value, 'Rotativa'))
        );
});

test('the turnos filter narrows the report to workers assigned to a matching shift', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $morningWorker = User::factory()->for($organization)->employee()->create(['name' => 'Mañana']);
    $eveningWorker = User::factory()->for($organization)->employee()->create(['name' => 'Tarde']);

    $morningShift = Shift::factory()->for($organization)->create();
    $eveningShift = Shift::factory()->for($organization)->create();

    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $morningWorker->id,
        'shift_id' => $morningShift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $eveningWorker->id,
        'shift_id' => $eveningShift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'shifts' => [$eveningShift->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report', 1)
            ->where('report.0.employee', fn (string $value) => str_contains($value, 'Tarde'))
        );
});

test('a shift assignment ended before the range does not match the turnos filter', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $worker = User::factory()->for($organization)->employee()->create();
    $shift = Shift::factory()->for($organization)->create();

    // Assignment ended in 2025, before the March 2026 report range.
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $worker->id,
        'shift_id' => $shift->id,
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
            'shifts' => [$shift->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('report', 0));
});

test('the checksum filter narrows the report to the worker owning the matching mark', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $owner = User::factory()->for($organization)->employee()->create(['name' => 'Dueña']);
    $other = User::factory()->for($organization)->employee()->create(['name' => 'Ajena']);

    $mark = Mark::factory()->for($organization)->create([
        'user_id' => $owner->id,
        'date_time' => '2026-03-02 09:00:00',
    ]);
    Mark::factory()->for($organization)->create([
        'user_id' => $other->id,
        'date_time' => '2026-03-02 09:00:00',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'checksum' => $mark->checksum,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report', 1)
            ->where('report.0.employee', fn (string $value) => str_contains($value, 'Dueña'))
        );
});

test('an unknown checksum returns an empty report', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    User::factory()->for($organization)->employee()->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-02',
            'checksum' => 'does-not-exist',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('report', 0));
});
