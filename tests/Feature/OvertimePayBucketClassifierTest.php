<?php

use App\Enums\OvertimeCalculationState;
use App\Enums\OvertimeCompensationType;
use App\Models\Holiday;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\User;
use App\Models\Workday;
use App\Services\Overtime\OvertimePayBucketClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * A computed day carrying the given calculated overtime, mirroring the
 * helper in OvertimeExportDatasetTest so both suites stay consistent.
 *
 * @return array{0: Workday, 1: User, 2: Organization}
 */
function payBucketDay(string $date, string $calculatedOvertime, ?Organization $organization = null, ?User $employee = null): array
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

// --- AC #7: a normal weekday, fully authorised and paid ---

test('a fully authorised weekday lands entirely in the ordinary-day bucket', function () {
    [$workday, $supervisor] = payBucketDay('2026-08-04', '02:00:00'); // a Tuesday

    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $breakdown = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$workday->user_id])
        ->get($workday->user_id);

    expect((string) $breakdown->ordinaryDayHours)->toBe('02:00:00')
        ->and($breakdown->sundayOrHolidayHours->isZero())->toBeTrue()
        ->and($breakdown->compensatedInRestDaysHours->isZero())->toBeTrue()
        ->and($breakdown->unauthorizedHours->isZero())->toBeTrue();
});

// --- AC #2: Sunday and holiday work route to the sunday-or-holiday bucket ---

test('overtime worked on a Sunday lands in the sunday-or-holiday bucket', function () {
    [$workday, $supervisor] = payBucketDay('2026-08-02', '01:30:00'); // a Sunday

    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $breakdown = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$workday->user_id])
        ->get($workday->user_id);

    expect((string) $breakdown->sundayOrHolidayHours)->toBe('01:30:00')
        ->and($breakdown->ordinaryDayHours->isZero())->toBeTrue();
});

test('overtime worked on a public holiday lands in the sunday-or-holiday bucket', function () {
    $organization = Organization::factory()->create();
    Holiday::factory()->create(['date' => '2026-09-18', 'organization_id' => null]); // a Friday

    [$workday, $supervisor] = payBucketDay('2026-09-18', '01:00:00', $organization);

    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $breakdown = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), [$workday->user_id])
        ->get($workday->user_id);

    expect((string) $breakdown->sundayOrHolidayHours)->toBe('01:00:00');
});

// --- AC #4: a partially authorised day splits between payable and unauthorised ---

test('a day authorised for less than calculated splits between its payable bucket and unauthorised', function () {
    [$workday, $supervisor] = payBucketDay('2026-08-04', '03:00:00'); // a Tuesday

    OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, authorizedHours: '02:00:00', reason: 'Solo se autorizan 2 horas.');

    $breakdown = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$workday->user_id])
        ->get($workday->user_id);

    expect((string) $breakdown->ordinaryDayHours)->toBe('02:00:00')
        ->and((string) $breakdown->unauthorizedHours)->toBe('01:00:00');
});

// --- AC #4: a fully unauthorised day (no decision ever opened) ---

test('a day with no authorisation record at all is reported entirely as unauthorised', function () {
    [$workday] = payBucketDay('2026-08-04', '01:45:00'); // no OvertimeAuthorization::openFor() call

    $breakdown = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$workday->user_id])
        ->get($workday->user_id);

    expect((string) $breakdown->unauthorizedHours)->toBe('01:45:00')
        ->and($breakdown->ordinaryDayHours->isZero())->toBeTrue()
        ->and($breakdown->sundayOrHolidayHours->isZero())->toBeTrue()
        ->and($breakdown->compensatedInRestDaysHours->isZero())->toBeTrue();
});

// --- KOL-47: rest-day compensation is authorised but not money-payable ---

test('a day authorised for rest-day compensation, still unexpired, lands in its own bucket rather than a payable one', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id, 'overtime_rest_day_eligible' => true]);

    [$workday, $supervisor] = payBucketDay('2026-08-04', '02:00:00', $organization, $employee);

    OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.', compensationType: OvertimeCompensationType::RestDays);

    $breakdown = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$employee->id])
        ->get($employee->id);

    expect((string) $breakdown->compensatedInRestDaysHours)->toBe('02:00:00')
        ->and($breakdown->ordinaryDayHours->isZero())->toBeTrue()
        ->and($breakdown->sundayOrHolidayHours->isZero())->toBeTrue()
        ->and($breakdown->unauthorizedHours->isZero())->toBeTrue();
});

// --- AC #6: organization scoping ---

test('an employee from another organization is never blended into a requested user id', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $employeeA = User::factory()->create(['organization_id' => $orgA->id]);
    $adminA = User::factory()->create(['organization_id' => $orgA->id]);
    [$workdayA, $supervisorA] = payBucketDay('2026-08-04', '01:00:00', $orgA, $employeeA);
    OvertimeAuthorization::openFor($workdayA)->approve($supervisorA, reason: 'Sin pacto vigente para esta fecha.');

    $employeeB = User::factory()->create(['organization_id' => $orgB->id]);
    [$workdayB, $supervisorB] = payBucketDay('2026-08-04', '01:00:00', $orgB, $employeeB);
    OvertimeAuthorization::openFor($workdayB)->approve($supervisorB, reason: 'Sin pacto vigente para esta fecha.');

    $this->actingAs($adminA);

    $breakdowns = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$employeeA->id, $employeeB->id]);

    expect((string) $breakdowns->get($employeeA->id)->ordinaryDayHours)->toBe('01:00:00')
        ->and($breakdowns->get($employeeB->id)->ordinaryDayHours->isZero())->toBeTrue();
});

// --- AC #5: the four buckets reconcile exactly with calculated_overtime ---

test('the four buckets sum exactly to the period calculated_overtime total, nothing lost or double-counted', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id, 'overtime_rest_day_eligible' => true]);

    [$fullyAuthorized, $supervisor] = payBucketDay('2026-08-03', '02:00:00', $organization, $employee); // Monday
    OvertimeAuthorization::openFor($fullyAuthorized)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    [$partiallyAuthorized] = payBucketDay('2026-08-04', '03:00:00', $organization, $employee); // Tuesday
    OvertimeAuthorization::openFor($partiallyAuthorized)
        ->approve($supervisor, authorizedHours: '01:00:00', reason: 'Solo se autoriza 1 hora.');

    [$restDayCompensated] = payBucketDay('2026-08-05', '01:30:00', $organization, $employee); // Wednesday
    OvertimeAuthorization::openFor($restDayCompensated)
        ->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.', compensationType: OvertimeCompensationType::RestDays);

    [$neverDecided] = payBucketDay('2026-08-06', '00:45:00', $organization, $employee); // Thursday, no authorization opened

    $expectedTotalSeconds = (2 * 3600) + (3 * 3600) + (1.5 * 3600) + (0.75 * 3600);

    $breakdown = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$employee->id])
        ->get($employee->id);

    $bucketTotalSeconds = $breakdown->ordinaryDayHours->seconds
        + $breakdown->sundayOrHolidayHours->seconds
        + $breakdown->compensatedInRestDaysHours->seconds
        + $breakdown->unauthorizedHours->seconds;

    expect($bucketTotalSeconds)->toBe((int) $expectedTotalSeconds);
});

// --- AC #6: recomputation cannot silently move an already-payable figure ---

test('recalculating a workday after approval does not change its already-classified payable bucket', function () {
    [$workday, $supervisor] = payBucketDay('2026-08-04', '02:00:00'); // a Tuesday

    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $before = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$workday->user_id])
        ->get($workday->user_id);

    // A later recalculation raises the engine's figure (a corrected mark, a
    // shift reassignment) without going through a new decision.
    $workday->forceFill(['calculated_overtime' => '05:00:00'])->save();

    $after = app(OvertimePayBucketClassifier::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$workday->user_id])
        ->get($workday->user_id);

    expect((string) $after->ordinaryDayHours)->toBe((string) $before->ordinaryDayHours)
        ->and((string) $after->ordinaryDayHours)->toBe('02:00:00')
        // The grown, undecided excess surfaces as unauthorised rather than
        // silently repricing the payable figure.
        ->and((string) $after->unauthorizedHours)->toBe('03:00:00')
        ->and($workday->fresh()->overtimeNeedsReReview())->toBeTrue();
});
