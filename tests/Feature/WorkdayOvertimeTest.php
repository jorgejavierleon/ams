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
 * Jornadas. KOL-80 then made the decision lazy: nothing creates an
 * OvertimeAuthorization row ahead of a decision any more, "objecting" is
 * gone (silence is refusal), and an approved record can be revoked instead.
 * These tests exercise the WorkdayController routes against that model.
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
 * A workday with calculated overtime, within every legal cap and covered by
 * a pacto so a plain approval needs no justification. No OvertimeAuthorization
 * row exists yet — KOL-80 dropped eager creation entirely.
 */
function workdayOvertimeDay(User $employee, string $date, string $calculatedOvertime = '01:00:00', string $workedTime = '09:00:00'): Workday
{
    OvertimePact::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);

    return Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'worked_time' => $workedTime,
        'post_shift_excess' => $calculatedOvertime,
        'calculated_overtime' => $calculatedOvertime,
        'overtime_state' => OvertimeCalculationState::forCalculatedOvertime($calculatedOvertime),
        'overtime_calculated_at' => now(),
    ]);
}

function workdayOvertimeFlaggedDay(User $employee, string $date): Workday
{
    return Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'worked_time' => '09:00:00',
        'calculated_overtime' => '01:00:00',
        'anomaly_flags' => [AnomalyFlagReason::NoAssignedShift->value],
    ]);
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

test('the index surfaces calculated overtime as not opened when nobody has decided it', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $workday = workdayOvertimeDay($employee, '2026-08-03', '01:30:00');

    $this->actingAs($admin)
        ->get(route('workdays.index', ['from' => '2026-08-03', 'to' => '2026-08-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('workdays.data.0.overtime.calculated_hours', '01:30:00')
            ->where('workdays.data.0.overtime.status', 'not_opened')
            ->where('workdays.data.0.overtime.can_decide', true)
            ->where('workdays.data.0.overtime.can_revoke', false));

    expect(OvertimeAuthorization::query()->where('workday_id', $workday->id)->exists())->toBeFalse();
});

// --- Lazy creation: the core of KOL-80 ---

test('approving a day with no prior authorisation row creates it already decided', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $workday = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    expect(OvertimeAuthorization::query()->count())->toBe(0);

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), [
            'authorized_hours' => '01:00',
        ])
        ->assertRedirect();

    expect(OvertimeAuthorization::query()->count())->toBe(1);

    $authorization = OvertimeAuthorization::query()->where('workday_id', $workday->id)->firstOrFail();

    expect($authorization->isApproved())->toBeTrue()
        ->and($authorization->authorized_hours)->toBe('01:00:00');
});

test('confirming no code path creates an OvertimeAuthorization row for a day nobody has acted on', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $workday = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->get(route('workdays.index', ['from' => '2026-08-03', 'to' => '2026-08-03']))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('workdays.show', $workday->id))
        ->assertOk();

    expect(OvertimeAuthorization::query()->count())->toBe(0);
});

test('re-clicking approve on an already-approved day is refused', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $workday = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), ['authorized_hours' => '01:00'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), ['authorized_hours' => '01:00'])
        ->assertForbidden();
});

test('approving a flagged day is refused with the flag reason', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $workday = workdayOvertimeFlaggedDay($employee, '2026-08-03');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), [])
        ->assertSessionHasErrors('reason');

    $authorization = OvertimeAuthorization::query()->where('workday_id', $workday->id)->first();

    expect($authorization?->isPending())->toBeTrue();
});

test('approving beyond the daily overtime cap is refused without a justification and accepted with one', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    // 3h exceeds the 2h daily overtime cap.
    $workday = workdayOvertimeDay($employee, '2026-08-03', '03:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), [
            'authorized_hours' => '03:00',
        ])
        ->assertSessionHasErrors('reason');

    $authorization = OvertimeAuthorization::query()->where('workday_id', $workday->id)->firstOrFail();

    expect($authorization->isPending())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), [
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

    $workday = workdayOvertimeDay($stranger, '2026-08-03', '01:00:00');

    $this->actingAs($supervisor)
        ->post(route('workdays.overtime.approve', $workday->id), [
            'authorized_hours' => '01:00',
        ])
        ->assertForbidden();

    expect(OvertimeAuthorization::query()->where('workday_id', $workday->id)->exists())->toBeFalse();
});

test('a supervisor decides their own reports overtime', function () {
    $organization = Organization::factory()->create();
    $supervisor = workdayOvertimeSupervisor($organization);
    $report = workdayOvertimeEmployee($organization, $supervisor);

    $workday = workdayOvertimeDay($report, '2026-08-03', '01:00:00');

    $this->actingAs($supervisor)
        ->post(route('workdays.overtime.approve', $workday->id), [
            'authorized_hours' => '01:00',
        ])
        ->assertRedirect();

    expect(OvertimeAuthorization::query()->where('workday_id', $workday->id)->firstOrFail()->isApproved())->toBeTrue();
});

// --- Revoke ---

test('an admin revokes a days approved overtime, and it appears in the timeline', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $workday = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), ['authorized_hours' => '01:00'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('workdays.overtime.revoke', $workday->id), [
            'reason' => 'Aprobación registrada por error.',
        ])
        ->assertRedirect();

    $authorization = OvertimeAuthorization::query()->where('workday_id', $workday->id)->firstOrFail();

    expect($authorization->isRevoked())->toBeTrue()
        ->and($authorization->revoked_reason)->toBe('Aprobación registrada por error.')
        ->and($authorization->revoked_by)->toBe($admin->id)
        ->and($workday->fresh()->authorizedOvertime()->isZero())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('workdays.show', $workday->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('timeline', 1)
            ->where('timeline.0.kind', 'overtime')
            ->where('timeline.0.status', 'revoked')
            ->where('timeline.0.revoked_by', $admin->name));
});

test('revoking a day with no approved record is refused', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $workday = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.revoke', $workday->id), ['reason' => 'No hay nada que revocar.'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), ['authorized_hours' => '01:00'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('workdays.overtime.revoke', $workday->id), ['reason' => 'Primera revocación.'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('workdays.overtime.revoke', $workday->id), ['reason' => 'Segunda revocación.'])
        ->assertForbidden();
});

// --- Bulk decide ---

test('bulk-approving a mix of already-approved and undecided days only decides the undecided ones', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $alreadyApproved = workdayOvertimeDay($employee, '2026-08-02', '01:00:00');
    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $alreadyApproved->id), ['authorized_hours' => '01:00'])
        ->assertRedirect();
    $approvedAuthorization = OvertimeAuthorization::query()->where('workday_id', $alreadyApproved->id)->firstOrFail();

    $undecided = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.bulk-decide'), [
            'workdays' => [$alreadyApproved->id, $undecided->id],
        ])
        ->assertRedirect();

    expect($approvedAuthorization->fresh()->authorized_hours)->toBe('01:00:00')
        ->and(OvertimeAuthorization::query()->where('workday_id', $undecided->id)->firstOrFail()->isApproved())->toBeTrue()
        ->and(OvertimeAuthorization::query()->count())->toBe(2);
});

test('bulk-approving a selection decides the clean days and leaves a flagged one pending', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $clean = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');
    $flagged = workdayOvertimeFlaggedDay($employee, '2026-08-04');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.bulk-decide'), [
            'workdays' => [$clean->id, $flagged->id],
        ])
        ->assertRedirect();

    expect(OvertimeAuthorization::query()->where('workday_id', $clean->id)->firstOrFail()->isApproved())->toBeTrue()
        ->and(OvertimeAuthorization::query()->where('workday_id', $flagged->id)->firstOrFail()->isPending())->toBeTrue();
});

// --- KOL-47 rest-day compensation, from its new home ---

test('an eligible employees overtime can be approved for rest-day compensation from Jornadas', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);
    $employee->update(['overtime_rest_day_eligible' => true]);

    $workday = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), [
            'authorized_hours' => '01:00',
            'compensation_type' => 'rest_days',
        ])
        ->assertRedirect();

    $authorization = OvertimeAuthorization::query()->where('workday_id', $workday->id)->firstOrFail();

    expect($authorization->compensation_type)->toBe(OvertimeCompensationType::RestDays);
    $this->assertDatabaseHas('overtime_rest_day_balances', [
        'overtime_authorization_id' => $authorization->id,
    ]);
});

test('requesting rest-day compensation for an ineligible employee is rejected with a field-specific error', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $workday = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->post(route('workdays.overtime.approve', $workday->id), [
            'authorized_hours' => '01:00',
            'compensation_type' => 'rest_days',
        ])
        ->assertSessionHasErrors('compensation_type');

    $authorization = OvertimeAuthorization::query()->where('workday_id', $workday->id)->firstOrFail();

    expect($authorization->isPending())->toBeTrue();
});

// --- Merged detail-page timeline ---

test('the day detail page exposes the overtime summary before any decision exists', function () {
    $organization = Organization::factory()->create();
    $admin = workdayOvertimeAdmin($organization);
    $employee = workdayOvertimeEmployee($organization);

    $workday = workdayOvertimeDay($employee, '2026-08-03', '01:00:00');

    $this->actingAs($admin)
        ->get(route('workdays.show', $workday->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('overtime.calculated_hours', '01:00')
            ->where('overtime.status', 'not_opened')
            ->has('timeline', 0));
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
