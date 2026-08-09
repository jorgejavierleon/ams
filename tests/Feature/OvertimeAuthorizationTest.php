<?php

use App\Enums\OvertimeAuthorizationStatus;
use App\Enums\OvertimeCalculationState;
use App\Exceptions\OvertimeDecisionRefused;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
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

    $authorization = OvertimeAuthorization::openFor($workday)->approve($supervisor);

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
        ->approve($supervisor, authorizedHours: '04:00:00');

    expect($authorization->authorized_hours)->toBe('04:00:00')
        ->and($authorization->final_hours)->toBe('01:00:00');
});

test('an objected day pays nothing and leaves every worked hour visible as unauthorised', function () {
    [$workday, $supervisor] = overtimeDay('02:30:00');

    $authorization = OvertimeAuthorization::openFor($workday)
        ->object($supervisor, reason: 'No hubo autorización previa de la jefatura.');

    expect($authorization->isObjected())->toBeTrue()
        ->and($authorization->final_hours)->toBe('00:00:00')
        ->and((string) $authorization->authorizedOvertime())->toBe('00:00:00')
        ->and((string) $authorization->unauthorizedOvertime())->toBe('02:30:00')
        ->and($authorization->reason)->toBe('No hubo autorización previa de la jefatura.')
        ->and($authorization->reviewed_by)->toBe($supervisor->id);

    expect(OvertimeAuthorization::approved()->count())->toBe(0);
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
        ->toBe(['pending', 'approved', 'objected']);
});

test('the record is optionally linked to the pacto covering its worked date', function () {
    [$workday] = overtimeDay();

    $authorization = OvertimeAuthorization::openFor($workday);

    expect($authorization->overtime_pact_id)->toBeNull();

    $authorization->update(['overtime_pact_id' => 42]);

    expect($authorization->fresh()->overtime_pact_id)->toBe(42);
});

test('the scopes separate a period into what payroll may read and what it may not', function () {
    [$workday, $supervisor, $organization] = overtimeDay('02:00:00');

    OvertimeAuthorization::factory()->approved($supervisor)->create([
        'organization_id' => $organization->id,
        'workday_id' => $workday->id,
        'user_id' => $workday->user_id,
        'date' => $workday->date,
    ]);

    [$objectedWorkday] = overtimeDay('01:00:00', $organization);
    OvertimeAuthorization::factory()->objected($supervisor)->create([
        'organization_id' => $organization->id,
        'workday_id' => $objectedWorkday->id,
        'user_id' => $objectedWorkday->user_id,
        'date' => $objectedWorkday->date,
    ]);

    [$pendingWorkday] = overtimeDay('03:00:00', $organization);
    OvertimeAuthorization::openFor($pendingWorkday);

    $period = Carbon::parse('2026-08-01');

    expect(OvertimeAuthorization::approved()->betweenDates($period, $period->copy()->endOfMonth())->count())->toBe(1)
        ->and(OvertimeAuthorization::objected()->count())->toBe(1)
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

test('a tenant cannot approve or object to another tenant record', function () {
    [$workday] = overtimeDay('03:00:00');
    $authorization = OvertimeAuthorization::openFor($workday);

    $intruder = User::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    expect(fn () => $authorization->approve($intruder))->toThrow(OvertimeDecisionRefused::class);
    expect(fn () => $authorization->object($intruder, 'No.'))->toThrow(OvertimeDecisionRefused::class);

    expect($authorization->fresh()->status)->toBe(OvertimeAuthorizationStatus::Pending);
});
