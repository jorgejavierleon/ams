<?php

use App\Enums\AnomalyFlagReason;
use App\Enums\OvertimeCalculationState;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimePact;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The HTTP-level tests for the queue (KOL-44). The domain guarantees
 * themselves — the anomaly block, the cap justification, tenant isolation —
 * are already covered at the model layer by WorkdayAnomalyFlagsTest,
 * OvertimeCapValidationTest and OvertimeAuthorizationTest; these tests only
 * assert that the queue's controller reaches them correctly and never finds
 * a way around them.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function queueSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

function queueEmployee(Organization $organization, ?User $supervisor = null): User
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
function queueDay(User $employee, string $date, string $calculatedOvertime = '01:00:00', string $workedTime = '09:00:00'): OvertimeAuthorization
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

/**
 * A pending overtime authorisation whose workday carries an unresolved
 * anomaly flag — data not trustworthy enough to approve from (KOL-40).
 */
function queueFlaggedDay(User $employee, string $date): OvertimeAuthorization
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

test('a supervisor approves a direct reports pending overtime individually', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $employee = queueEmployee($organization, $supervisor);

    $authorization = queueDay($employee, '2026-08-03');

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.approve', $authorization), [
            'authorized_hours' => '01:00',
        ])
        ->assertRedirect();

    expect($authorization->fresh()->isApproved())->toBeTrue()
        ->and($authorization->fresh()->authorized_hours)->toBe('01:00:00');
});

test('the queues editable authorised-hours field lets a supervisor authorise fewer hours than were calculated', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $employee = queueEmployee($organization, $supervisor);

    $authorization = queueDay($employee, '2026-08-03', calculatedOvertime: '02:00:00');

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.approve', $authorization), [
            'authorized_hours' => '01:00',
            'reason' => 'Solo se autorizan dos horas de las tres calculadas.',
        ])
        ->assertRedirect();

    $authorization->refresh();

    expect($authorization->isApproved())->toBeTrue()
        ->and($authorization->calculated_hours)->toBe('02:00:00')
        ->and($authorization->authorized_hours)->toBe('01:00:00')
        ->and($authorization->final_hours)->toBe('01:00:00');
});

test('a supervisor objects to a direct reports pending overtime individually, leaving the raw marks untouched', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $employee = queueEmployee($organization, $supervisor);

    $authorization = queueDay($employee, '2026-08-03');
    $originalCalculatedOvertime = $authorization->workday->calculated_overtime;
    $originalMarkIn = $authorization->workday->mark_in_at;

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.object', $authorization), [
            'reason' => 'No corresponde: turno cubierto por otro motivo.',
        ])
        ->assertRedirect();

    $authorization->refresh();

    expect($authorization->isObjected())->toBeTrue()
        ->and($authorization->authorized_hours)->toBe('00:00:00')
        ->and($authorization->workday->fresh()->calculated_overtime)->toBe($originalCalculatedOvertime)
        ->and($authorization->workday->fresh()->mark_in_at->equalTo($originalMarkIn))->toBeTrue();
});

test('objecting without a reason is rejected and the record stays pending', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $employee = queueEmployee($organization, $supervisor);
    $authorization = queueDay($employee, '2026-08-03');

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.object', $authorization), [])
        ->assertSessionHasErrors('reason');

    expect($authorization->fresh()->isPending())->toBeTrue();
});

test('approving a flagged day is refused with the flag reason rather than a generic error', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $employee = queueEmployee($organization, $supervisor);
    $authorization = queueFlaggedDay($employee, '2026-08-03');

    $response = $this->actingAs($supervisor)
        ->post(route('overtime.queue.approve', $authorization), []);

    $response->assertSessionHasErrors('reason');
    expect(session('errors')->get('reason')[0])->toContain(AnomalyFlagReason::NoAssignedShift->label());
    expect($authorization->fresh()->isPending())->toBeTrue();
});

test('approving beyond the daily overtime cap is refused without a justification and accepted with one', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $employee = queueEmployee($organization, $supervisor);
    $authorization = queueDay($employee, '2026-08-03', calculatedOvertime: '03:00:00');

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.approve', $authorization), [])
        ->assertSessionHasErrors('reason');

    expect($authorization->fresh()->isPending())->toBeTrue();

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.approve', $authorization), [
            'reason' => 'Continuidad de servicio crítico.',
        ])
        ->assertRedirect();

    expect($authorization->fresh()->isApproved())->toBeTrue();
});

test('a supervisor cannot decide overtime for an employee outside their team', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $employee = queueEmployee($organization); // reports to nobody

    $authorization = queueDay($employee, '2026-08-03');

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.approve', $authorization), [])
        ->assertForbidden();

    expect($authorization->fresh()->isPending())->toBeTrue();
});

test('a supervisor bulk-approves several pending days for their team', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $employee = queueEmployee($organization, $supervisor);

    $first = queueDay($employee, '2026-08-03');
    $second = queueDay($employee, '2026-08-04');

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.bulk-decide'), [
            'ids' => [$first->id, $second->id],
            'action' => 'approve',
        ])
        ->assertRedirect();

    expect($first->fresh()->isApproved())->toBeTrue()
        ->and($second->fresh()->isApproved())->toBeTrue();
});

test('a bulk approval selection containing a flagged day leaves that day pending while deciding the rest', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $employee = queueEmployee($organization, $supervisor);

    $clean = queueDay($employee, '2026-08-03');
    $flagged = queueFlaggedDay($employee, '2026-08-04');

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.bulk-decide'), [
            'ids' => [$clean->id, $flagged->id],
            'action' => 'approve',
        ])
        ->assertRedirect();

    expect($clean->fresh()->isApproved())->toBeTrue()
        ->and($flagged->fresh()->isPending())->toBeTrue();
});

test('bulk decide cannot be used to decide overtime for an employee outside the supervisors team', function () {
    $organization = Organization::factory()->create();
    $supervisor = queueSupervisor($organization);
    $outsider = queueEmployee($organization); // reports to nobody

    $authorization = queueDay($outsider, '2026-08-03');

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.bulk-decide'), [
            'ids' => [$authorization->id],
            'action' => 'approve',
        ])
        ->assertRedirect();

    expect($authorization->fresh()->isPending())->toBeTrue();
});

test('a supervisor sees only their direct reports overtime while an admin sees the whole organization', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $supervisor = queueSupervisor($organization);
    $ownReport = queueEmployee($organization, $supervisor);
    $otherReport = queueEmployee($organization);

    queueDay($ownReport, '2026-08-03');
    queueDay($otherReport, '2026-08-03');

    $this->actingAs($supervisor)
        ->get(route('overtime.queue.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('overtime/queue/index')
            ->has('authorizations.data', 1)
        );

    $this->actingAs($admin)
        ->get(route('overtime.queue.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('overtime/queue/index')
            ->has('authorizations.data', 2)
        );
});

test('the queue stays bounded in query count regardless of how many employees have pending overtime', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    foreach (range(1, 60) as $i) {
        $employee = queueEmployee($organization);
        queueDay($employee, '2026-08-03');
    }

    DB::enableQueryLog();

    $this->actingAs($admin)
        ->get(route('overtime.queue.index'))
        ->assertOk();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The listing is paginated and eager-loaded, so the query count must not
    // scale with the number of employees carrying pending overtime — only
    // with the fixed set of relations loaded per page, plus the fixed pair
    // of nav-badge counts HandleInertiaRequests::pendingOvertimeCount() runs
    // on every request (KOL-66).
    expect($queryCount)->toBeLessThan(20);
});
