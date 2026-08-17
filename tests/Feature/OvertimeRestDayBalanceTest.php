<?php

use App\Enums\OvertimeCalculationState;
use App\Enums\OvertimeCompensationType;
use App\Exceptions\OvertimeDecisionRefused;
use App\Exceptions\RestDayBalanceRefused;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRestDayBalance;
use App\Models\User;
use App\Models\Workday;
use App\Services\Overtime\RestDayBalanceService;
use App\Support\Duration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * A computed day carrying the given calculated overtime, its employee and a
 * supervisor from the same organization — mirrors the helper in
 * OvertimeAuthorizationTest, kept local so this file stays self-contained.
 *
 * @return array{0: Workday, 1: User, 2: Organization}
 */
function restDayBalanceDay(string $date, string $calculatedOvertime, ?Organization $organization = null, ?User $employee = null): array
{
    $organization ??= Organization::factory()->create();
    $employee ??= User::factory()->create(['organization_id' => $organization->id]);
    $supervisor = User::factory()->create(['organization_id' => $organization->id]);

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'post_shift_excess' => $calculatedOvertime,
        'calculated_overtime' => $calculatedOvertime,
        'overtime_state' => OvertimeCalculationState::forCalculatedOvertime($calculatedOvertime),
        'overtime_calculated_at' => now(),
    ]);

    return [$workday, $supervisor, $organization];
}

// --- AC #8: the approver's choice, gated by the employee's standing eligibility flag ---

test('approving with rest-days compensation for an eligible employee accrues a balance at the statutory 1.5x ratio', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id, 'overtime_rest_day_eligible' => true]);

    [$workday, $supervisor] = restDayBalanceDay('2026-08-15', '01:00:00', $organization, $employee);

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.', compensationType: OvertimeCompensationType::RestDays);

    expect($authorization->compensation_type)->toBe(OvertimeCompensationType::RestDays);

    $balance = OvertimeRestDayBalance::where('overtime_authorization_id', $authorization->id)->sole();

    expect($balance->accrued_hours)->toBe('01:00:00')
        ->and($balance->rest_hours)->toBe('01:30:00')
        ->and($balance->consumed_hours)->toBe('00:00:00')
        ->and($balance->accrual_date->toDateString())->toBe('2026-08-15')
        ->and($balance->expiry_date->toDateString())->toBe('2027-02-15')
        ->and($balance->user_id)->toBe($employee->id)
        ->and($balance->organization_id)->toBe($organization->id);
});

test('omitting a compensation type defaults to payment, even for an eligible employee — a fallback nobody can configure', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id, 'overtime_rest_day_eligible' => true]);

    [$workday, $supervisor] = restDayBalanceDay('2026-08-15', '01:00:00', $organization, $employee);

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    expect($authorization->compensation_type)->toBe(OvertimeCompensationType::Payment)
        ->and(OvertimeRestDayBalance::where('overtime_authorization_id', $authorization->id)->exists())->toBeFalse();
});

test('requesting rest-day compensation for an ineligible employee is refused, and the record is left unchanged', function () {
    [$workday, $supervisor] = restDayBalanceDay('2026-08-15', '01:00:00');

    $authorization = OvertimeAuthorization::openFor($workday);

    expect(fn () => $authorization->approve(
        $supervisor,
        reason: 'Sin pacto vigente para esta fecha.',
        compensationType: OvertimeCompensationType::RestDays,
    ))->toThrow(OvertimeDecisionRefused::class);

    expect($authorization->fresh()->isPending())->toBeTrue()
        ->and(OvertimeRestDayBalance::where('overtime_authorization_id', $authorization->id)->exists())->toBeFalse();
});

test('AC #8: eligibility is per employee, independent of one another — never a tenant-wide default', function () {
    $organization = Organization::factory()->create();

    $eligibleEmployee = User::factory()->create(['organization_id' => $organization->id, 'overtime_rest_day_eligible' => true]);
    $ineligibleEmployee = User::factory()->create(['organization_id' => $organization->id, 'overtime_rest_day_eligible' => false]);

    [$eligibleWorkday, $supervisor] = restDayBalanceDay('2026-08-15', '01:00:00', $organization, $eligibleEmployee);
    [$ineligibleWorkday] = restDayBalanceDay('2026-08-15', '01:00:00', $organization, $ineligibleEmployee);

    $eligibleAuthorization = OvertimeAuthorization::openFor($eligibleWorkday)
        ->approve($supervisor, reason: 'Sin pacto.', compensationType: OvertimeCompensationType::RestDays);

    $ineligibleAuthorization = OvertimeAuthorization::openFor($ineligibleWorkday);

    expect($eligibleAuthorization->compensation_type)->toBe(OvertimeCompensationType::RestDays)
        ->and(OvertimeRestDayBalance::where('overtime_authorization_id', $eligibleAuthorization->id)->exists())->toBeTrue();

    expect(fn () => $ineligibleAuthorization->approve(
        $supervisor,
        reason: 'Sin pacto.',
        compensationType: OvertimeCompensationType::RestDays,
    ))->toThrow(OvertimeDecisionRefused::class);
});

// --- AC #2: consumption and traceability ---

test('partially consuming a balance decrements it and records a traceable consumption', function () {
    $balance = OvertimeRestDayBalance::factory()->create([
        'rest_hours' => '03:00:00',
        'consumed_hours' => '00:00:00',
    ]);
    $employee = User::find($balance->user_id);

    $consumptions = app(RestDayBalanceService::class)->consume(
        $employee,
        Duration::fromTimeString('01:00:00'),
        note: 'Descanso tomado el viernes.',
    );

    $balance->refresh();

    expect($balance->consumed_hours)->toBe('01:00:00')
        ->and((string) $balance->remaining())->toBe('02:00:00')
        ->and($balance->isFullyConsumed())->toBeFalse()
        ->and($consumptions)->toHaveCount(1)
        ->and($consumptions->first()->overtime_rest_day_balance_id)->toBe($balance->id)
        ->and($consumptions->first()->hours)->toBe('01:00:00')
        ->and($consumptions->first()->note)->toBe('Descanso tomado el viernes.');
});

test('consuming exactly the full balance leaves nothing remaining', function () {
    $balance = OvertimeRestDayBalance::factory()->create([
        'rest_hours' => '01:30:00',
        'consumed_hours' => '00:00:00',
    ]);
    $employee = User::find($balance->user_id);

    app(RestDayBalanceService::class)->consume($employee, Duration::fromTimeString('01:30:00'));

    $balance->refresh();

    expect($balance->consumed_hours)->toBe('01:30:00')
        ->and($balance->remaining()->isZero())->toBeTrue()
        ->and($balance->isFullyConsumed())->toBeTrue();
});

test('consuming across two accrual lines draws oldest-expiring first and traces both', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);

    $expiringSoon = OvertimeRestDayBalance::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'rest_hours' => '01:00:00',
        'accrual_date' => '2026-01-01',
        'expiry_date' => '2026-07-01',
    ]);
    $expiringLater = OvertimeRestDayBalance::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'rest_hours' => '02:00:00',
        'accrual_date' => '2026-03-01',
        'expiry_date' => '2026-09-01',
    ]);

    $consumptions = app(RestDayBalanceService::class)->consume(
        $employee,
        Duration::fromTimeString('01:30:00'),
    );

    expect($consumptions)->toHaveCount(2);

    $expiringSoon->refresh();
    $expiringLater->refresh();

    expect($expiringSoon->remaining()->isZero())->toBeTrue()
        ->and((string) $expiringLater->remaining())->toBe('01:30:00');
});

test('consuming more than the available balance is refused, and nothing is written', function () {
    $balance = OvertimeRestDayBalance::factory()->create([
        'rest_hours' => '01:00:00',
        'consumed_hours' => '00:00:00',
    ]);
    $employee = User::find($balance->user_id);

    expect(fn () => app(RestDayBalanceService::class)->consume($employee, Duration::fromTimeString('02:00:00')))
        ->toThrow(RestDayBalanceRefused::class);

    $balance->refresh();

    expect($balance->consumed_hours)->toBe('00:00:00')
        ->and($balance->consumptions()->count())->toBe(0);
});

// --- AC #3: expiry is visible, not deleted, and converts to payable ---

test('sweeping past-expiry lines stamps them expired without deleting them', function () {
    $balance = OvertimeRestDayBalance::factory()->pastExpiry()->create([
        'rest_hours' => '03:00:00',
        'consumed_hours' => '01:00:00',
    ]);

    $swept = app(RestDayBalanceService::class)->sweepExpired();

    $balance->refresh();

    expect($swept)->toBe(1)
        ->and($balance->isExpired())->toBeTrue()
        ->and($balance->expired_at)->not->toBeNull()
        ->and(OvertimeRestDayBalance::find($balance->id))->not->toBeNull();
});

test('an expired unconsumed remainder becomes payable, converted back out of the 1.5x ratio', function () {
    // 3h rest_hours accrued from 2h overtime, 1h consumed -> 2h remaining ->
    // 2/1.5 = 1h20m payable.
    $balance = OvertimeRestDayBalance::factory()->pastExpiry()->create([
        'accrued_hours' => '02:00:00',
        'rest_hours' => '03:00:00',
        'consumed_hours' => '01:00:00',
    ]);

    app(RestDayBalanceService::class)->sweepExpired();
    $balance->refresh();

    expect((string) $balance->payableFromExpiry())->toBe('01:20:00');
});

test('a fully consumed line that expires has nothing payable', function () {
    $balance = OvertimeRestDayBalance::factory()->pastExpiry()->create([
        'rest_hours' => '01:30:00',
        'consumed_hours' => '01:30:00',
    ]);

    app(RestDayBalanceService::class)->sweepExpired();
    $balance->refresh();

    expect($balance->isExpired())->toBeTrue()
        ->and($balance->payableFromExpiry()->isZero())->toBeTrue();
});

test('a line not yet past its expiry date is left untouched by the sweep', function () {
    $balance = OvertimeRestDayBalance::factory()->create();

    $swept = app(RestDayBalanceService::class)->sweepExpired();

    $balance->refresh();

    expect($swept)->toBe(0)
        ->and($balance->isExpired())->toBeFalse();
});

// --- AC #4: structural exclusion from the payable/export scope ---

test('an approved rest-day-compensated authorization never satisfies the exportable scope, even after its balance expires', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id, 'overtime_rest_day_eligible' => true]);

    [$workday, $supervisor] = restDayBalanceDay('2025-01-15', '01:00:00', $organization, $employee);

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Sin pacto.', compensationType: OvertimeCompensationType::RestDays);

    expect(OvertimeAuthorization::exportable()->find($authorization->id))->toBeNull();

    // Move well past the six-month expiry window and sweep. The expired
    // remainder becomes payable through the balance line itself (see the
    // sibling expiry tests) — the authorization row is never the path that
    // pays it; that is a distinct, separate source (see KOL-49 notes).
    Carbon::setTestNow('2025-09-01');
    app(RestDayBalanceService::class)->sweepExpired();

    expect(OvertimeAuthorization::exportable()->find($authorization->id))->toBeNull();

    $balance = OvertimeRestDayBalance::where('overtime_authorization_id', $authorization->id)->sole();
    expect($balance->isExpired())->toBeTrue();

    Carbon::setTestNow();
});

test('an approved payment-compensated authorization satisfies the exportable scope', function () {
    [$workday, $supervisor] = restDayBalanceDay('2026-08-15', '01:00:00');

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    expect(OvertimeAuthorization::exportable()->find($authorization->id))->not->toBeNull();
});

test('a pending or objected record never satisfies the exportable scope', function () {
    [$workday, $supervisor] = restDayBalanceDay('2026-08-15', '01:00:00');

    $pending = OvertimeAuthorization::openFor($workday);

    expect(OvertimeAuthorization::exportable()->find($pending->id))->toBeNull();

    $pending->object($supervisor, reason: 'Horas no autorizadas.');

    expect(OvertimeAuthorization::exportable()->find($pending->id))->toBeNull();
});
