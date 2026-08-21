<?php

use App\Enums\OvertimeAuthorizationStatus;
use App\Enums\OvertimeCalculationState;
use App\Exceptions\OvertimeDecisionRefused;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimePact;
use App\Models\User;
use App\Models\Workday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * A computed day carrying the given calculated overtime (OHC), its employee and
 * a supervisor from the same organization.
 *
 * @return array{0: Workday, 1: User, 2: Organization}
 */
function overtimeDay(string $calculatedOvertime = '03:00:00', ?Organization $organization = null): array
{
    $organization ??= Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $supervisor = User::factory()->create(['organization_id' => $organization->id]);

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-03'),
        'post_shift_excess' => $calculatedOvertime,
        'calculated_overtime' => $calculatedOvertime,
        'overtime_state' => OvertimeCalculationState::forCalculatedOvertime($calculatedOvertime),
        'overtime_calculated_at' => now(),
    ]);

    return [$workday, $supervisor, $organization];
}

test('opening a record snapshots the three figures separately and starts pending', function () {
    [$workday] = overtimeDay('03:00:00');

    $authorization = OvertimeAuthorization::openFor($workday, requestedHours: '02:00:00');

    expect($authorization->status)->toBe(OvertimeAuthorizationStatus::Pending)
        ->and($authorization->calculated_hours)->toBe('03:00:00')
        ->and($authorization->requested_hours)->toBe('02:00:00')
        ->and($authorization->authorized_hours)->toBeNull()
        ->and($authorization->final_hours)->toBeNull()
        ->and($authorization->user_id)->toBe($workday->user_id)
        ->and($authorization->date->toDateString())->toBe($workday->date->toDateString());
});

test('a day has exactly one authorisation record', function () {
    [$workday] = overtimeDay();

    $first = OvertimeAuthorization::openFor($workday);
    $second = OvertimeAuthorization::openFor($workday);

    expect($second->id)->toBe($first->id)
        ->and(OvertimeAuthorization::where('workday_id', $workday->id)->count())->toBe(1);
});

test('a fully authorised day pays everything that was calculated', function () {
    [$workday, $supervisor] = overtimeDay('03:00:00');

    // 3h exceeds the 2h daily overtime cap in force on 2026-08-03 (KOL-41), so
    // the approval needs a justification.
    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Continuidad de servicio crítico.');

    expect($authorization->isApproved())->toBeTrue()
        ->and($authorization->authorized_hours)->toBe('03:00:00')
        ->and($authorization->final_hours)->toBe('03:00:00')
        ->and((string) $authorization->authorizedOvertime())->toBe('03:00:00')
        ->and((string) $authorization->unauthorizedOvertime())->toBe('00:00:00')
        ->and($authorization->reviewed_by)->toBe($supervisor->id)
        ->and($authorization->reviewed_at)->not->toBeNull();

    expect((string) $workday->fresh()->authorizedOvertime())->toBe('03:00:00');
});

test('a partially authorised day pays what was authorised and keeps the rest queryable as unauthorised', function () {
    [$workday, $supervisor] = overtimeDay('03:00:00');

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, authorizedHours: '02:00:00', reason: 'Solo se autorizan dos horas.');

    expect($authorization->calculated_hours)->toBe('03:00:00')
        ->and($authorization->authorized_hours)->toBe('02:00:00')
        ->and($authorization->final_hours)->toBe('02:00:00')
        ->and((string) $authorization->unauthorizedOvertime())->toBe('01:00:00')
        ->and($authorization->reason)->toBe('Solo se autorizan dos horas.');

    // The unauthorised hour is never merged into the payable total.
    $workday = $workday->fresh();

    expect((string) $workday->authorizedOvertime())->toBe('02:00:00')
        ->and((string) $workday->unauthorizedOvertime())->toBe('01:00:00');
});

test('authorising more hours than were worked pays only what was calculated', function () {
    [$workday, $supervisor] = overtimeDay('01:00:00');

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, authorizedHours: '04:00:00', reason: 'Autorización de prueba.');

    expect($authorization->authorized_hours)->toBe('04:00:00')
        ->and($authorization->final_hours)->toBe('01:00:00');
});

test('authorising more than was requested pays the full authorised amount when it was all worked', function () {
    // The requested figure (OHR) is recorded but stays outside the MIN
    // comparison: an employee who asked for one hour but was authorised and
    // worked three is paid three, not capped back down to the request.
    [$workday, $supervisor] = overtimeDay('03:00:00');

    $authorization = OvertimeAuthorization::openFor($workday, requestedHours: '01:00:00')
        ->approve($supervisor, authorizedHours: '03:00:00', reason: 'Se autorizan las tres horas trabajadas.');

    expect($authorization->requested_hours)->toBe('01:00:00')
        ->and($authorization->authorized_hours)->toBe('03:00:00')
        ->and($authorization->final_hours)->toBe('03:00:00');
});

test('a missing calculated figure is excluded from the comparison rather than flooring the payable amount to zero', function () {
    [$workday, $supervisor] = overtimeDay('03:00:00');

    $authorization = OvertimeAuthorization::openFor($workday);
    $authorization->forceFill(['calculated_hours' => null])->save();

    $authorization->approve($supervisor, authorizedHours: '02:00:00', reason: 'Autorización sin cifra calculada.');

    expect($authorization->final_hours)->toBe('02:00:00');
});

test('pure post-hoc mode with no request stays pending until a human confirms it, and a cap breach demands justification', function () {
    // No OHR anywhere on the record — the fallback of PRD §7.1 for pure Mode
    // B. The figure is the calculated one, subject to the same legal-cap
    // validation (KOL-41) as every other day, and never auto-approved.
    [$workday, $supervisor] = overtimeDay('03:00:00');

    $authorization = OvertimeAuthorization::openFor($workday);

    expect($authorization->requested_hours)->toBeNull()
        ->and($authorization->isPending())->toBeTrue();

    // 3h exceeds the 2h daily overtime cap in force on 2026-08-03, so
    // approving without a reason is refused rather than silently blocked or
    // silently waved through (decision-1: a flag, never a bar).
    expect(fn () => $authorization->approve($supervisor))
        ->toThrow(OvertimeDecisionRefused::class);

    $authorization->refresh();
    expect($authorization->isPending())->toBeTrue();

    $authorization->approve($supervisor, reason: 'Continuidad de servicio crítico.');

    expect($authorization->isApproved())->toBeTrue()
        ->and($authorization->final_hours)->toBe('03:00:00');
});

test('a recalculation that raises the figure after approval does not raise the payable amount, and surfaces the day for re-review', function () {
    [$workday, $supervisor] = overtimeDay('02:00:00');

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Autorización de prueba.');

    expect($authorization->final_hours)->toBe('02:00:00');

    $decided = $workday->fresh();
    expect($decided->overtime_decided_at)->not->toBeNull()
        ->and($decided->overtime_decided_value)->toBe('02:00:00')
        ->and($decided->overtimeNeedsReReview())->toBeFalse();

    // A mark correction lets the engine recompute a bigger figure. The engine
    // never touches the authorisation (KOL-39), so the approved record keeps
    // paying what it already decided.
    $workday->update(['calculated_overtime' => '03:00:00']);

    expect($authorization->fresh()->final_hours)->toBe('02:00:00')
        ->and($workday->fresh()->overtimeNeedsReReview())->toBeTrue();
});

test('a revoked day pays nothing and leaves every worked hour visible as unauthorised', function () {
    [$workday, $supervisor] = overtimeDay('02:30:00');

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Autorización de prueba.')
        ->revoke($supervisor, 'Aprobación registrada por error.');

    expect($authorization->isRevoked())->toBeTrue()
        ->and((string) $authorization->authorizedOvertime())->toBe('00:00:00')
        ->and((string) $authorization->unauthorizedOvertime())->toBe('02:30:00')
        ->and($authorization->revoked_reason)->toBe('Aprobación registrada por error.')
        ->and($authorization->revoked_by)->toBe($supervisor->id)
        // The original approval's own audit trail is untouched by the revocation.
        ->and($authorization->reviewed_by)->toBe($supervisor->id)
        ->and($authorization->reason)->toBe('Autorización de prueba.');

    expect(OvertimeAuthorization::approved()->count())->toBe(0);
});

test('revoking a record that was never approved is refused', function () {
    [$workday, $supervisor] = overtimeDay('02:30:00');

    $authorization = OvertimeAuthorization::openFor($workday);

    expect(fn () => $authorization->revoke($supervisor, 'Nada que revocar.'))
        ->toThrow(OvertimeDecisionRefused::class);

    expect($authorization->fresh()->isPending())->toBeTrue();
});

test('a day nobody decided authorises nothing, whatever the engine calculated', function () {
    [$workday] = overtimeDay('02:00:00');

    $authorization = OvertimeAuthorization::openFor($workday);

    expect((string) $authorization->authorizedOvertime())->toBe('00:00:00')
        ->and((string) $authorization->unauthorizedOvertime())->toBe('02:00:00')
        ->and((string) $workday->fresh()->authorizedOvertime())->toBe('00:00:00');
});

test('a day with no authorisation record at all authorises nothing', function () {
    [$workday] = overtimeDay('02:00:00');

    expect($workday->overtimeAuthorization)->toBeNull()
        ->and((string) $workday->authorizedOvertime())->toBe('00:00:00')
        ->and((string) $workday->unauthorizedOvertime())->toBe('02:00:00');
});

test('no amount of elapsed time moves a pending record to approved', function () {
    [$workday] = overtimeDay();

    $authorization = OvertimeAuthorization::openFor($workday);

    $this->travel(1)->years();

    // The scheduled consolidation of mark modifications is the one place in the
    // system where silence approves something. It must not reach overtime.
    $this->artisan('mark-modifications:approve-overdue')->assertSuccessful();

    $authorization->refresh();

    expect($authorization->status)->toBe(OvertimeAuthorizationStatus::Pending)
        ->and($authorization->reviewed_by)->toBeNull()
        ->and($authorization->final_hours)->toBeNull()
        ->and(OvertimeAuthorization::approved()->count())->toBe(0)
        ->and(OvertimeAuthorization::pending()->count())->toBe(1);
});

test('a record cannot be persisted as approved without the person who decided it', function () {
    [$workday] = overtimeDay();

    $authorization = OvertimeAuthorization::openFor($workday);

    expect(fn () => $authorization->forceFill([
        'status' => OvertimeAuthorizationStatus::Approved,
        'authorized_hours' => '03:00:00',
        'final_hours' => '03:00:00',
    ])->save())->toThrow(OvertimeDecisionRefused::class);

    expect($authorization->fresh()->status)->toBe(OvertimeAuthorizationStatus::Pending);
});

test('a record cannot be created as approved without the person who decided it', function () {
    [$workday, , $organization] = overtimeDay();

    expect(fn () => OvertimeAuthorization::create([
        'organization_id' => $organization->id,
        'workday_id' => $workday->id,
        'user_id' => $workday->user_id,
        'date' => $workday->date,
        'calculated_hours' => '03:00:00',
        'status' => OvertimeAuthorizationStatus::Approved,
    ]))->toThrow(OvertimeDecisionRefused::class);

    expect(OvertimeAuthorization::withoutGlobalScopes()->count())->toBe(0);
});

test('an enum with no lapsed case is what makes the timeout impossible', function () {
    expect(array_column(OvertimeAuthorizationStatus::cases(), 'value'))
        ->toBe(['pending', 'approved', 'revoked']);
});

test('the record is optionally linked to the pacto covering its worked date', function () {
    [$workday, , $organization] = overtimeDay();

    $authorization = OvertimeAuthorization::openFor($workday);

    expect($authorization->overtime_pact_id)->toBeNull();

    $pact = OvertimePact::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $workday->user_id,
    ]);
    $authorization->update(['overtime_pact_id' => $pact->id]);

    expect($authorization->fresh()->overtime_pact_id)->toBe($pact->id);
});

test('the scopes separate a period into what payroll may read and what it may not', function () {
    [$workday, $supervisor, $organization] = overtimeDay('02:00:00');

    OvertimeAuthorization::factory()->approved($supervisor)->create([
        'organization_id' => $organization->id,
        'workday_id' => $workday->id,
        'user_id' => $workday->user_id,
        'date' => $workday->date,
        'reason' => 'Autorización de prueba.',
    ]);

    [$revokedWorkday] = overtimeDay('01:00:00', $organization);
    OvertimeAuthorization::factory()->revoked($supervisor)->create([
        'organization_id' => $organization->id,
        'workday_id' => $revokedWorkday->id,
        'user_id' => $revokedWorkday->user_id,
        'date' => $revokedWorkday->date,
    ]);

    [$pendingWorkday] = overtimeDay('03:00:00', $organization);
    OvertimeAuthorization::openFor($pendingWorkday);

    $period = Carbon::parse('2026-08-01');

    expect(OvertimeAuthorization::approved()->betweenDates($period, $period->copy()->endOfMonth())->count())->toBe(1)
        ->and(OvertimeAuthorization::revoked()->count())->toBe(1)
        ->and(OvertimeAuthorization::pending()->count())->toBe(1);
});

test('a tenant never reads another tenant authorisation record', function () {
    [$otherWorkday] = overtimeDay('03:00:00');
    $otherAuthorization = OvertimeAuthorization::openFor($otherWorkday);

    [, , $organization] = overtimeDay('01:00:00');
    $reader = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($reader);

    expect(OvertimeAuthorization::find($otherAuthorization->id))->toBeNull()
        ->and(OvertimeAuthorization::count())->toBe(0);
});

test('a tenant cannot approve or revoke another tenant record', function () {
    [$workday, $supervisor] = overtimeDay('03:00:00');
    $authorization = OvertimeAuthorization::openFor($workday);

    $intruder = User::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    expect(fn () => $authorization->approve($intruder))->toThrow(OvertimeDecisionRefused::class);
    expect($authorization->fresh()->status)->toBe(OvertimeAuthorizationStatus::Pending);

    $authorization->approve($supervisor, reason: 'Continuidad de servicio crítico.');

    expect(fn () => $authorization->revoke($intruder, 'No.'))->toThrow(OvertimeDecisionRefused::class);
    expect($authorization->fresh()->status)->toBe(OvertimeAuthorizationStatus::Approved);
});
