<?php

use App\Enums\OvertimeCalculationState;
use App\Exceptions\OvertimeDecisionRefused;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\User;
use App\Models\Workday;
use App\Services\LegalHourLimitVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * A computed day for one employee, with the worked and calculated-overtime
 * spans set independently so a test can isolate the overtime cap from the
 * combined ordinary-plus-extraordinary ceiling.
 *
 * @return array{0: Workday, 1: User, 2: Organization}
 */
function capDay(
    string $date,
    string $calculatedOvertime,
    string $workedTime = '10:00:00',
    ?Organization $organization = null,
    ?User $employee = null,
): array {
    $organization ??= Organization::factory()->create();
    $employee ??= User::factory()->create(['organization_id' => $organization->id]);
    $supervisor = User::factory()->create(['organization_id' => $organization->id]);

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'worked_time' => $workedTime,
        'post_shift_excess' => $calculatedOvertime,
        'calculated_overtime' => $calculatedOvertime,
        'overtime_state' => OvertimeCalculationState::forCalculatedOvertime($calculatedOvertime),
        'overtime_calculated_at' => now(),
    ]);

    return [$workday, $supervisor, $organization];
}

// The baseline legal limits (see the legal_hour_limits migration) hold
// max_overtime_daily_hours at 2h and max_overtime_weekly_hours at 12h across
// every version, so every date in these tests resolves to the same overtime
// ceiling regardless of which version is in force.
test('a day within every cap approves without a justification', function () {
    [$workday, $supervisor] = capDay('2026-08-03', calculatedOvertime: '01:00:00', workedTime: '09:00:00');

    $authorization = OvertimeAuthorization::openFor($workday)->approve($supervisor);

    expect($authorization->isApproved())->toBeTrue()
        ->and($authorization->final_hours)->toBe('01:00:00')
        ->and($authorization->reason)->toBeNull();
});

test('a day over the daily overtime cap is refused without a justification and accepted with one', function () {
    [$workday, $supervisor] = capDay('2026-08-03', calculatedOvertime: '03:00:00');

    $authorization = OvertimeAuthorization::openFor($workday);

    expect(fn () => $authorization->approve($supervisor))
        ->toThrow(OvertimeDecisionRefused::class);
    expect($authorization->fresh()->isPending())->toBeTrue();

    $authorization->approve($supervisor, reason: 'Continuidad de servicio crítico.');

    expect($authorization->fresh()->isApproved())->toBeTrue()
        ->and($authorization->fresh()->final_hours)->toBe('03:00:00')
        ->and($authorization->fresh()->reason)->toBe('Continuidad de servicio crítico.');
});

test('a week pushed over the weekly overtime cap by an individually valid day is refused without a justification', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);

    // Six prior weekdays (Mon 3 Aug – Sat 8 Aug 2026), each approved for
    // exactly the 2h daily cap - individually valid, none needing a
    // justification - totalling 12h for the week already. Worked time equals
    // the overtime span (no ordinary hours) so only the overtime ceiling,
    // never the combined one, is what these days test.
    foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07', '2026-08-08'] as $date) {
        [$priorWorkday, $priorSupervisor] = capDay($date, calculatedOvertime: '02:00:00', workedTime: '02:00:00', organization: $organization, employee: $employee);

        OvertimeAuthorization::openFor($priorWorkday)->approve($priorSupervisor);
    }

    // Sunday 9 Aug: only half an hour, well inside the daily cap on its own,
    // but the week is already spent - PRD §7.3's own example.
    [$sundayWorkday, $sundaySupervisor] = capDay('2026-08-09', calculatedOvertime: '00:30:00', workedTime: '00:30:00', organization: $organization, employee: $employee);

    $authorization = OvertimeAuthorization::openFor($sundayWorkday);

    expect(fn () => $authorization->approve($sundaySupervisor))
        ->toThrow(OvertimeDecisionRefused::class);
    expect($authorization->fresh()->isPending())->toBeTrue();

    $authorization->approve($sundaySupervisor, reason: 'Semana ya al tope; se autoriza igual por continuidad operativa.');

    expect($authorization->fresh()->isApproved())->toBeTrue();
});

test('a day breaching the combined ordinary-plus-extraordinary daily ceiling is refused without a justification', function () {
    // 2h of overtime is exactly at the daily overtime cap (not exceeding it),
    // but 10h30 of ordinary work alongside it pushes the combined day past the
    // 12h max_total_daily_hours ceiling.
    [$workday, $supervisor] = capDay('2026-08-03', calculatedOvertime: '02:00:00', workedTime: '12:30:00');

    $authorization = OvertimeAuthorization::openFor($workday);

    expect(fn () => $authorization->approve($supervisor))
        ->toThrow(OvertimeDecisionRefused::class);
    expect($authorization->fresh()->isPending())->toBeTrue();

    $authorization->approve($supervisor, reason: 'Jornada combinada excepcional autorizada.');

    expect($authorization->fresh()->isApproved())->toBeTrue()
        ->and($authorization->fresh()->final_hours)->toBe('02:00:00');
});

test('a cap change between the worked date and the approval date is judged by the worked date version, not today', function () {
    Carbon::setTestNow('2026-08-09 10:00:00');

    // Worked on 3 Aug, while the 2h/12h overtime ceiling was in force.
    [$workday, $supervisor] = capDay('2026-08-03', calculatedOvertime: '02:00:00');

    // A stricter ceiling takes effect from 5 Aug - after the day was worked,
    // before it is approved "today".
    app(LegalHourLimitVersions::class)->add([
        'effective_from' => '2026-08-05',
        'ordinary_weekly_hours' => 42,
        'ordinary_daily_hours' => 10,
        'max_overtime_daily_hours' => 1,
        'max_overtime_weekly_hours' => 6,
        'max_total_daily_hours' => 12,
        'max_total_weekly_hours' => 48,
        'legal_reference' => 'Ley ficticia',
    ]);

    // Under the new version 2h would breach the 1h daily cap; under the
    // version in force when the hours were actually worked it does not.
    $authorization = OvertimeAuthorization::openFor($workday)->approve($supervisor);

    expect($authorization->isApproved())->toBeTrue()
        ->and($authorization->reason)->toBeNull();
});
