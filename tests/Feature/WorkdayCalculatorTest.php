<?php

use App\Enums\MarkType;
use App\Enums\WorkdayStatus;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use App\Models\Workday;
use App\Services\WorkdayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * A Monday with an 08:00–17:00 shift (one-hour lunch) assigned to the employee.
 *
 * @return array{0: User, 1: Carbon}
 */
function employeeOnShift(): array
{
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);

    $date = Carbon::parse('next monday')->startOfDay();

    $shift = Shift::factory()->create(['organization_id' => $organization->id]);
    ShiftDay::factory()->create([
        'shift_id' => $shift->id,
        // ShiftDay weekdays are 0=Monday … 6=Sunday.
        'weekday' => (int) $date->format('N') - 1,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'lunch_start_time' => '12:00:00',
        'lunch_end_time' => '13:00:00',
        'is_free' => false,
    ]);
    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
        'start_date' => $date->copy()->subWeek()->toDateString(),
        'end_date' => null,
    ]);

    return [$employee, $date];
}

function punch(User $employee, MarkType $type, Carbon $at): Mark
{
    return Mark::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => $type,
        'date_time' => $at,
    ]);
}

test('a full day against a shift is regular and nets worked time minus lunch', function () {
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    app(WorkdayCalculator::class)->calculateDate($date);

    $workday = Workday::withoutGlobalScopes()->where('user_id', $employee->id)->firstOrFail();

    expect($workday->status)->toBe(WorkdayStatus::Regular)
        ->and($workday->worked_time)->toBe('08:00:00')
        ->and($workday->in_time_difference)->toBe('00:00:00');
});

test('a scheduled shift with no marks is an absence', function () {
    [$employee, $date] = employeeOnShift();

    app(WorkdayCalculator::class)->calculateDate($date);

    $workday = Workday::withoutGlobalScopes()->where('user_id', $employee->id)->firstOrFail();

    expect($workday->status)->toBe(WorkdayStatus::Absent);
});

test('a single mark is an incomplete day', function () {
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(8, 0));

    app(WorkdayCalculator::class)->calculateDate($date);

    $workday = Workday::withoutGlobalScopes()->where('user_id', $employee->id)->firstOrFail();

    expect($workday->status)->toBe(WorkdayStatus::Incomplete);
});

test('marks without any scheduled shift are irregular', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $date = Carbon::parse('next monday')->startOfDay();

    punch($employee, MarkType::In, $date->copy()->setTime(9, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(18, 0));

    app(WorkdayCalculator::class)->calculateDate($date);

    $workday = Workday::withoutGlobalScopes()->where('user_id', $employee->id)->firstOrFail();

    expect($workday->status)->toBe(WorkdayStatus::Irregular);
});

test('calculateDate does not create a second workday for a day already computed', function () {
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    $calculator = app(WorkdayCalculator::class);
    $calculator->calculateDate($date);
    $calculator->calculateDate($date);

    expect(Workday::withoutGlobalScopes()->where('user_id', $employee->id)->count())->toBe(1);
});
