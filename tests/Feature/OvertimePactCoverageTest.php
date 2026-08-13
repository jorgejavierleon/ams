<?php

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
 * A computed day carrying the given calculated overtime, its employee and a
 * supervisor from the same organization — mirrors the helper in
 * OvertimeAuthorizationTest, kept local so this file stays self-contained.
 *
 * @return array{0: Workday, 1: User, 2: Organization}
 */
function pactCoverageDay(string $date, string $calculatedOvertime = '01:00:00'): array
{
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
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

test('approving links the record to the pacto covering its worked date', function () {
    [$workday, $supervisor, $organization] = pactCoverageDay('2026-08-15');

    $pact = OvertimePact::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $workday->user_id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);

    $authorization = OvertimeAuthorization::openFor($workday)->approve($supervisor);

    expect($authorization->overtime_pact_id)->toBe($pact->id)
        ->and($authorization->reason)->toBeNull();
});

// AC #4 and DoD #8: validity is judged by the date worked, not the date
// approved. A decision made after the pacto's own term has lapsed still finds
// it, because what matters is whether it covered 15 Aug when the hours were
// worked - not whether it still covers "today".
test('a decision made after the pacto has expired still finds it, because it covered the date worked', function () {
    [$workday, $supervisor, $organization] = pactCoverageDay('2026-08-15');

    $pact = OvertimePact::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $workday->user_id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);

    // Approving on 15 September - a month after the pacto's own end_date.
    Carbon::setTestNow('2026-09-15 10:00:00');

    $authorization = OvertimeAuthorization::openFor($workday)->approve($supervisor);

    expect($authorization->overtime_pact_id)->toBe($pact->id)
        ->and($authorization->reason)->toBeNull();
});

test('a pacto created after the record opened is still found at approval time', function () {
    [$workday, $supervisor, $organization] = pactCoverageDay('2026-08-15');

    $authorization = OvertimeAuthorization::openFor($workday);

    // The pacto is agreed after the record was already opened as pending.
    $pact = OvertimePact::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $workday->user_id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);

    $authorization->approve($supervisor);

    expect($authorization->overtime_pact_id)->toBe($pact->id);
});

test('a pacto for a different date does not cover this record', function () {
    [$workday, $supervisor, $organization] = pactCoverageDay('2026-08-15');

    OvertimePact::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $workday->user_id,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
    ]);

    expect(fn () => OvertimeAuthorization::openFor($workday)->approve($supervisor))
        ->toThrow(OvertimeDecisionRefused::class);
});

test('a revoked pacto does not cover the record even if its dates match', function () {
    [$workday, $supervisor, $organization] = pactCoverageDay('2026-08-15');

    OvertimePact::factory()->revoked()->create([
        'organization_id' => $organization->id,
        'user_id' => $workday->user_id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);

    expect(fn () => OvertimeAuthorization::openFor($workday)->approve($supervisor))
        ->toThrow(OvertimeDecisionRefused::class);
});

// AC #7: absence of a pacto never blocks payment - it only demands a reason.
test('approving without a covering pacto is refused without a justification and accepted with one', function () {
    [$workday, $supervisor] = pactCoverageDay('2026-08-15');

    $authorization = OvertimeAuthorization::openFor($workday);

    expect(fn () => $authorization->approve($supervisor))
        ->toThrow(OvertimeDecisionRefused::class);
    expect($authorization->fresh()->isPending())->toBeTrue();

    $authorization->approve($supervisor, reason: 'Sin pacto vigente; se autoriza por continuidad operativa.');

    expect($authorization->fresh()->isApproved())->toBeTrue()
        ->and($authorization->fresh()->overtime_pact_id)->toBeNull()
        ->and($authorization->fresh()->final_hours)->toBe('01:00:00')
        ->and($authorization->fresh()->reason)->toBe('Sin pacto vigente; se autoriza por continuidad operativa.');
});
