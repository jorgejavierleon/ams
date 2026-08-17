<?php

use App\Enums\AnomalyFlagReason;
use App\Enums\OvertimeCalculationState;
use App\Enums\OvertimeCompensationType;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimePact;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * KOL-71: overtime approval moved from the standalone queue (KOL-44) onto
 * Jornadas. These tests exercise the new WorkdayController routes and the
 * team-scoped Workday permissions (ViewTeam/ApproveTeam:Workday) that had to
 * be introduced for a supervisor to reach the page at all — previously
 * WorkdayController was reachable only by admins.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function workdayOvertimeAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function workdayOvertimeSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

function workdayOvertimeEmployee(Organization $organization, ?User $supervisor = null): User
{
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'supervisor_id' => $supervisor?->id,
    ]);
    $employee->assignRole('employee');

    return $employee;
}

/**
 * A pending overtime authorisation within every legal cap, covered by a
 * pacto so a plain approval needs no justification.
 */
function workdayOvertimeDay(User $employee, string $date, string $calculatedOvertime = '01:00:00', string $workedTime = '09:00:00'): OvertimeAuthorization
{
    OvertimePact::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);

    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'worked_time' => $workedTime,
        'post_shift_excess' => $calculatedOvertime,
        'calculated_overtime' => $calculatedOvertime,
        'overtime_state' => OvertimeCalculationState::forCalculatedOvertime($calculatedOvertime),
        'overtime_calculated_at' => now(),
    ]);

    return OvertimeAuthorization::openFor($workday);
}

function workdayOvertimeFlaggedDay(User $employee, string $date): OvertimeAuthorization
{
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'worked_time' => '09:00:00',
        'calculated_overtime' => '01:00:00',
        'anomaly_flags' => [AnomalyFlagReason::NoAssignedShift->value],
    ]);

    return OvertimeAuthorization::openFor($workday);
}

// --- Access control: the permission gap this task had to close ---

test('a supervisor without ViewTeam:Workday cannot reach Jornadas', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $employee->assignRole('employee');

    $this->actingAs($employee)->get(route('workdays.index'))->assertForbidden();
});

test('a supervisor with ViewTeam:Workday reaches Jornadas and sees only their own reports', function () {
    $organization = Organization::factory()->create();
    $supervisor = workdayOvertimeSupervisor($organization);
    $mine = workdayOvertimeEmployee($organization, $supervisor);
    $other = workdayOvertimeEmployee($organization);

    Workday::factory()->create(['organization_id' => $organization->id, 'user_id' => $mine->id, 'date' => '2026-08-03']);
    Workday::factory()->create(['organization_id' => $organization->id, 'user_id' => $other->id, 'date' => '2026-08-03']);

    $this->actingAs($supervisor)
        ->get(route('workdays.index', ['from' => '2026-08-03', 'to' => '2026-08-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('workdays.data', 1)
            ->where('workdays.data.0.employee', $mine->name));
});

test('an admin sees the whole organizations workdays regardless of supervisor', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    Workday::factory()->create(['organization_id' => $organization->id, 'user_id' => $employee->id, 'date' => '2026-08-03']);

    $this->actingAs($admin)
        ->get(route('workdays.index', ['from' => '2026-08-03', 'to' => '2026-08-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('workdays.data', 1));
});

test('the index surfaces overtime hours and status on a row with calculated overtime', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $authorization = workdayOvertimeDay($employee, '2026-08-03', '01:30:00');

    $this->actingAs($admin)
        ->get(route('workdays.index', ['from' => '2026-08-03', 'to' => '2026-08-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('workdays.data.0.overtime.calculated_hours', '01:30:00')
            ->where('workdays.data.0.overtime.status', 'pending')
            ->where('workdays.data.0.overtime.can_decide', true));

    expect($authorization->isPending())->toBeTrue();
});

// --- Individual approve/object ---

test('an admin approves a days overtime individually from Jornadas', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $authorization = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $authorization->workday_id), [
            'authorized_hours' => '01:00',
        ])
        ->assertRedirect();

    expect($authorization->fresh()->isApproved())->toBeTrue()
        ->and($authorization->fresh()->authorized_hours)->toBe('01:00:00');
});

test('an admin objects to a days overtime individually from Jornadas', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $authorization = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.object', $authorization->workday_id), [
            'reason' => 'No hubo autorización previa de la jefatura.',
        ])
        ->assertRedirect();

    expect($authorization->fresh()->isObjected())->toBeTrue();
});

test('approving a flagged day is refused with the flag reason', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $authorization = workdayOvertimeFlaggedDay($employee, '2026-08-03');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $authorization->workday_id), [])
        ->assertSessionHasErrors('reason');

    expect($authorization->fresh()->isPending())->toBeTrue();
});

test('approving beyond the daily overtime cap is refused without a justification and accepted with one', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    // 3h exceeds the 2h daily overtime cap.
    $authorization = workdayOvertimeDay($employee, '2026-08-03', '03:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $authorization->workday_id), [
            'authorized_hours' => '03:00',
        ])
        ->assertSessionHasErrors('reason');

    expect($authorization->fresh()->isPending())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $authorization->workday_id), [
            'authorized_hours' => '03:00',
            'reason' => 'Continuidad de servicio crítico.',
        ])
        ->assertRedirect();

    expect($authorization->fresh()->isApproved())->toBeTrue();
});

test('a supervisor cannot decide overtime for an employee outside their team', function () {
    $organization = Organization::factory()->create();
    $supervisor = workdayOvertimeSupervisor($organization);
    $stranger = workdayOvertimeEmployee($organization);

    $authorization = workdayOvertimeDay($stranger, '2026-08-03', '01:00:00');

    $this->actingAs($supervisor)
        ->post(route('workdays.overtime.approve', $authorization->workday_id), [
            'authorized_hours' => '01:00',
        ])
        ->assertForbidden();

    expect($authorization->fresh()->isPending())->toBeTrue();
});

test('a supervisor decides their own reports overtime', function () {
    $organization = Organization::factory()->create();
    $supervisor = workdayOvertimeSupervisor($organization);
    $report = workdayOvertimeEmployee($organization, $supervisor);

    $authorization = workdayOvertimeDay($report, '2026-08-03', '01:00:00');

    $this->actingAs($supervisor)
        ->post(route('workdays.overtime.approve', $authorization->workday_id), [
            'authorized_hours' => '01:00',
        ])
        ->assertRedirect();

    expect($authorization->fresh()->isApproved())->toBeTrue();
});

// --- Bulk decide ---

test('bulk-approving a selection decides the clean days and leaves a flagged one pending', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $clean = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');
    $flagged = workdayOvertimeFlaggedDay($employee, '2026-08-04');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.bulk-decide'), [
            'workdays' => [$clean->workday_id, $flagged->workday_id],
            'action' => 'approve',
        ])
        ->assertRedirect();

    expect($clean->fresh()->isApproved())->toBeTrue()
        ->and($flagged->fresh()->isPending())->toBeTrue();
});

// --- KOL-47 rest-day compensation, from its new home ---

test('an eligible employees overtime can be approved for rest-day compensation from Jornadas', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);
    $employee->update(['overtime_rest_day_eligible' => true]);

    $authorization = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $authorization->workday_id), [
            'authorized_hours' => '01:00',
            'compensation_type' => 'rest_days',
        ])
        ->assertRedirect();

    expect($authorization->fresh()->compensation_type)->toBe(OvertimeCompensationType::RestDays);
    $this->assertDatabaseHas('overtime_rest_day_balances', [
        'overtime_authorization_id' => $authorization->id,
    ]);
});

test('requesting rest-day compensation for an ineligible employee is rejected with a field-specific error', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $authorization = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $authorization->workday_id), [
            'authorized_hours' => '01:00',
            'compensation_type' => 'rest_days',
        ])
        ->assertSessionHasErrors('compensation_type');

    expect($authorization->fresh()->isPending())->toBeTrue();
});

// --- Merged detail-page timeline ---

test('the day detail page exposes the overtime summary and a merged timeline entry', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $authorization = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->get(route('workdays.show', $authorization->workday_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('overtime.calculated_hours', '01:00')
            ->where('overtime.status', 'pending')
            ->has('timeline', 1)
            ->where('timeline.0.kind', 'overtime'));
});

// --- Regression: bulkModify (mark corrections) must not crash for a
// supervisor now that ApproveTeam:Workday is a reachable, real permission ---

test('a supervisor can bulk-modify marks for their own teams workdays without error', function () {
    $organization = Organization::factory()->create();
    $supervisor = workdayOvertimeSupervisor($organization);
    $report = workdayOvertimeEmployee($organization, $supervisor);

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $report->id,
        'date' => Carbon::today()->subDays(5),
    ]);

    $this->actingAs($supervisor)
        ->post(route('workdays.bulk-modify'), [
            'workdays' => [$workday->id],
            'mark_type' => 'in',
            'time' => '09:00',
            'reason' => 'mark_forgotten',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mark_modifications', [
        'workday_id' => $workday->id,
    ]);
});
