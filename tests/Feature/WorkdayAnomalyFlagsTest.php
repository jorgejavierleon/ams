<?php

use App\Enums\AnomalyFlagReason;
use App\Enums\GeoStatus;
use App\Enums\MarkType;
use App\Enums\WorkdayStatus;
use App\Exceptions\OvertimeDecisionRefused;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workday;
use App\Services\OrganizationSettings;
use App\Services\WorkdayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * A Monday with an 08:00–17:00 shift assigned to the employee. Kept local to
 * this file, deliberately not shared with WorkdayCalculatorTest, so its
 * helpers do not depend on cross-file load order.
 *
 * @return array{0: User, 1: Carbon, 2: Organization}
 */
function flagEmployeeOnShift(): array
{
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);

    $date = Carbon::parse('next monday')->startOfDay();

    $shift = Shift::factory()->create(['organization_id' => $organization->id]);

    $shift->days()
        ->where('weekday', (int) $date->format('N') - 1)
        ->firstOrFail()
        ->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'lunch_start_time' => null,
            'lunch_end_time' => null,
            'is_free' => false,
        ]);

    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
        'start_date' => $date->copy()->subWeek()->toDateString(),
        'end_date' => null,
    ]);

    return [$employee, $date, $organization];
}

function flagPunch(User $employee, MarkType $type, Carbon $at, ?GeoStatus $geoStatus = null): Mark
{
    return Mark::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => $type,
        'date_time' => $at,
        'geo_status' => $geoStatus,
    ]);
}

function flagCalculate(Carbon $date): void
{
    app(WorkdayCalculator::class)->calculateDate($date);
}

function flaggedWorkday(User $employee, Carbon $date): Workday
{
    return Workday::withoutGlobalScopes()
        ->where('user_id', $employee->id)
        ->whereDate('date', $date)
        ->firstOrFail();
}

function weeklyAnomalyThreshold(User $employee, float $hours): void
{
    app(OrganizationSettings::class)
        ->current($employee->organization_id)
        ->update(['overtime_weekly_anomaly_threshold_hours' => $hours]);
}

test('a regular day with none of the anomaly conditions carries no flags', function () {
    [$employee, $date] = flagEmployeeOnShift();

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);

    expect($workday->isFlagged())->toBeFalse()
        ->and($workday->anomalyFlags())->toBe([]);
});

test('marks with no assigned shift are flagged, reusing the irregular status', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $date = Carbon::parse('next monday')->startOfDay();

    flagPunch($employee, MarkType::In, $date->copy()->setTime(9, 0));
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(18, 0));

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);

    expect($workday->status)->toBe(WorkdayStatus::Irregular)
        ->and($workday->anomalyFlags())->toBe([AnomalyFlagReason::NoAssignedShift]);
});

test('a single mark is flagged, reusing the incomplete status', function () {
    [$employee, $date] = flagEmployeeOnShift();

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0));

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);

    expect($workday->status)->toBe(WorkdayStatus::Incomplete)
        ->and($workday->anomalyFlags())->toBe([AnomalyFlagReason::IncompleteMarks]);
});

test('a day before the contract starts is flagged', function () {
    [$employee, $date] = flagEmployeeOnShift();
    $employee->update(['contract_start_date' => $date->copy()->addWeek()]);

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);

    expect($workday->anomalyFlags())->toBe([AnomalyFlagReason::ContractNotActive]);
});

test('a day after the contract ended is flagged', function () {
    [$employee, $date] = flagEmployeeOnShift();
    $employee->update(['contract_end_date' => $date->copy()->subWeek()]);

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);

    expect($workday->anomalyFlags())->toBe([AnomalyFlagReason::ContractNotActive]);
});

test('a contract with no start or end date recorded is never flagged as inactive', function () {
    [$employee, $date] = flagEmployeeOnShift();

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    flagCalculate($date);

    expect(flaggedWorkday($employee, $date)->isFlagged())->toBeFalse();
});

test('a mark outside the geofence is flagged', function () {
    [$employee, $date] = flagEmployeeOnShift();

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0), GeoStatus::Outside);
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);

    expect($workday->anomalyFlags())->toBe([AnomalyFlagReason::OutsideGeofence]);
});

test('a mark with an unknown geofence verdict is not flagged', function () {
    // Unknown covers a denied location permission, an unconfigured premise or
    // radius — none of them may be held against the employee (App\Enums\GeoStatus).
    [$employee, $date] = flagEmployeeOnShift();

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0), GeoStatus::Unknown);
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    flagCalculate($date);

    expect(flaggedWorkday($employee, $date)->isFlagged())->toBeFalse();
});

test('weekly overtime above the tenant threshold is flagged', function () {
    [$employee, $date] = flagEmployeeOnShift();
    weeklyAnomalyThreshold($employee, 1.0);

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(19, 0));

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);

    expect($workday->calculated_overtime)->toBe('02:00:00')
        ->and($workday->anomalyFlags())->toBe([AnomalyFlagReason::PeriodVolumeExceeded]);
});

test('weekly overtime at or under the tenant threshold is not flagged', function () {
    [$employee, $date] = flagEmployeeOnShift();
    weeklyAnomalyThreshold($employee, 2.0);

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(19, 0));

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);

    expect($workday->calculated_overtime)->toBe('02:00:00')
        ->and($workday->isFlagged())->toBeFalse();
});

test('a day can carry several flags at once', function () {
    [$employee, $date] = flagEmployeeOnShift();
    $employee->update(['contract_start_date' => $date->copy()->addWeek()]);

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0), GeoStatus::Outside);

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);

    expect($workday->status)->toBe(WorkdayStatus::Incomplete)
        ->and($workday->anomalyFlags())->toBe([
            AnomalyFlagReason::IncompleteMarks,
            AnomalyFlagReason::ContractNotActive,
            AnomalyFlagReason::OutsideGeofence,
        ]);
});

test('a flag clears once its cause is corrected and the day is recalculated', function () {
    [$employee, $date] = flagEmployeeOnShift();

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0));

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);
    expect($workday->anomalyFlags())->toBe([AnomalyFlagReason::IncompleteMarks]);

    flagPunch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    app(WorkdayCalculator::class)->recalculateWorkday($workday);

    expect($workday->fresh()->status)->toBe(WorkdayStatus::Regular)
        ->and($workday->fresh()->isFlagged())->toBeFalse();
});

test('flagging never blocks saving the marks or running the calculation', function () {
    // Resolución 38 art. 45.2: a flag is advisory at the point of entry. No
    // exception is thrown creating the marks or running calculateDate, however
    // untrustworthy the resulting day turns out to be.
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $employee->update(['contract_end_date' => Carbon::parse('next monday')->subWeek()]);
    $date = Carbon::parse('next monday')->startOfDay();

    $mark = flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0), GeoStatus::Outside);

    expect($mark->exists)->toBeTrue();

    expect(fn () => flagCalculate($date))->not->toThrow(Throwable::class);

    expect(flaggedWorkday($employee, $date)->isFlagged())->toBeTrue();
});

test('a flagged day cannot be approved and stays undecided', function () {
    [$employee, $date, $organization] = flagEmployeeOnShift();
    $supervisor = User::factory()->create(['organization_id' => $organization->id]);

    flagPunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    flagPunch($employee, MarkType::Out, $date->copy()->setTime(19, 0), GeoStatus::Outside);

    flagCalculate($date);

    $workday = flaggedWorkday($employee, $date);
    expect($workday->isFlagged())->toBeTrue();

    $authorization = OvertimeAuthorization::openFor($workday);

    expect(fn () => $authorization->approve($supervisor))->toThrow(OvertimeDecisionRefused::class);
    expect($authorization->fresh()->isPending())->toBeTrue();
});
