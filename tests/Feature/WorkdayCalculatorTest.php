<?php

use App\Enums\MarkType;
use App\Enums\WorkdayStatus;
use App\Models\Mark;
use App\Models\Organization;
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
 * A Monday with an 08:00–17:00 shift (one-hour lunch) assigned to the employee.
 * The times are overridable so overnight schedules can be staged the same way.
 *
 * @return array{0: User, 1: Carbon}
 */
function employeeOnShift(
    string $startTime = '08:00:00',
    string $endTime = '17:00:00',
    ?string $lunchStartTime = '12:00:00',
    ?string $lunchEndTime = '13:00:00',
): array {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);

    $date = Carbon::parse('next monday')->startOfDay();

    $shift = Shift::factory()->create(['organization_id' => $organization->id]);

    // A new shift is seeded with a day for every weekday, so the day in question
    // is reshaped rather than added — a second row for the same weekday would
    // have the calculator produce two candidate workdays for the one employee.
    // ShiftDay weekdays are 0=Monday … 6=Sunday.
    $shift->days()
        ->where('weekday', (int) $date->format('N') - 1)
        ->firstOrFail()
        ->update([
            'start_time' => $startTime,
            'end_time' => $endTime,
            'lunch_start_time' => $lunchStartTime,
            'lunch_end_time' => $lunchEndTime,
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

/**
 * Turn on counting the pre-shift excess towards OHC for the employee's tenant.
 */
function countPreShiftExcess(User $employee): void
{
    app(OrganizationSettings::class)
        ->current($employee->organization_id)
        ->update(['overtime_counts_pre_shift_excess' => true]);
}

/**
 * Run the calculator over the date and hand back the employee's computed day.
 */
function calculatedWorkday(User $employee, Carbon $date): Workday
{
    app(WorkdayCalculator::class)->calculateDate($date);

    return Workday::withoutGlobalScopes()->where('user_id', $employee->id)->firstOrFail();
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

test('a late arrival stays regular but records the positive in-time difference', function () {
    // The new app has no distinct LATE status: lateness against the shift start
    // is captured numerically in in_time_difference while the day, having both
    // marks against a scheduled shift, remains Regular.
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(8, 30));
    punch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    app(WorkdayCalculator::class)->calculateDate($date);

    $workday = Workday::withoutGlobalScopes()->where('user_id', $employee->id)->firstOrFail();

    expect($workday->status)->toBe(WorkdayStatus::Regular)
        ->and($workday->in_time_difference)->toBe('00:30:00')
        // Half an hour late off an eight-hour net shift leaves 7h30 worked.
        ->and($workday->worked_time)->toBe('07:30:00');
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

test('recalculateWorkday recomputes the totals after a mark is corrected', function () {
    // Models the effect of approving a mark modification: the underlying mark's
    // time is rewritten, then the workday is recalculated in place so its
    // status, worked time and shift deltas reflect the corrected punch.
    [$employee, $date] = employeeOnShift();

    $markIn = punch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    $calculator = app(WorkdayCalculator::class);
    $calculator->calculateDate($date);

    $workday = Workday::withoutGlobalScopes()->where('user_id', $employee->id)->firstOrFail();
    expect($workday->in_time_difference)->toBe('00:00:00')
        ->and($workday->worked_time)->toBe('08:00:00');

    // Correct the entry mark to half an hour late and recalculate in place.
    $markIn->update(['date_time' => $date->copy()->setTime(8, 30)]);

    expect($calculator->recalculateWorkday($workday))->toBeTrue();

    $workday->refresh();

    expect($workday->status)->toBe(WorkdayStatus::Regular)
        ->and($workday->in_time_difference)->toBe('00:30:00')
        ->and($workday->worked_time)->toBe('07:30:00');
});

// --- Shift excess and calculated overtime (OHC, PRD §7.2) ---

test('staying past the shift end is stored as a post-shift excess and is the calculated overtime', function () {
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(18, 30));

    $workday = calculatedWorkday($employee, $date);

    expect($workday->post_shift_excess)->toBe('01:30:00')
        ->and($workday->pre_shift_excess)->toBe('00:00:00')
        ->and($workday->calculated_overtime)->toBe('01:30:00');
});

test('an early arrival with an on-time exit produces no calculated overtime by default', function () {
    // The default policy: the pre-shift excess is recorded in full but does not
    // reach OHC, because nothing shows the employer asked for those two hours.
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(6, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    $workday = calculatedWorkday($employee, $date);

    expect($workday->pre_shift_excess)->toBe('02:00:00')
        ->and($workday->post_shift_excess)->toBe('00:00:00')
        ->and($workday->calculated_overtime)->toBe('00:00:00');
});

test('the same early arrival is calculated overtime once the organization counts it', function () {
    [$employee, $date] = employeeOnShift();
    countPreShiftExcess($employee);

    punch($employee, MarkType::In, $date->copy()->setTime(6, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    $workday = calculatedWorkday($employee, $date);

    expect($workday->pre_shift_excess)->toBe('02:00:00')
        ->and($workday->calculated_overtime)->toBe('02:00:00');
});

test('an early arrival combined with a late exit adds both excesses only under the enabling policy', function () {
    [$employee, $date] = employeeOnShift();
    countPreShiftExcess($employee);

    punch($employee, MarkType::In, $date->copy()->setTime(7, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(18, 45));

    $workday = calculatedWorkday($employee, $date);

    expect($workday->pre_shift_excess)->toBe('01:00:00')
        ->and($workday->post_shift_excess)->toBe('01:45:00')
        ->and($workday->calculated_overtime)->toBe('02:45:00');
});

test('both excesses are stored whatever the policy says, so enabling it later needs no recalculation', function () {
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(7, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(18, 0));

    $workday = calculatedWorkday($employee, $date);

    // Computed with the policy off: the excess is on the row regardless, and
    // only the OHC of days computed afterwards changes when it is turned on.
    expect($workday->pre_shift_excess)->toBe('01:00:00')
        ->and($workday->calculated_overtime)->toBe('01:00:00');

    countPreShiftExcess($employee);

    expect($workday->fresh()->pre_shift_excess)->toBe('01:00:00');
});

test('a day with no assigned shift has no calculated overtime at all', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $date = Carbon::parse('next monday')->startOfDay();

    punch($employee, MarkType::In, $date->copy()->setTime(9, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(20, 0));

    $workday = calculatedWorkday($employee, $date);

    // Eleven hours worked, but no schedule to have exceeded: nothing is inferred.
    expect($workday->status)->toBe(WorkdayStatus::Irregular)
        ->and($workday->pre_shift_excess)->toBeNull()
        ->and($workday->post_shift_excess)->toBeNull()
        ->and($workday->calculated_overtime)->toBeNull();
});

test('a day with only one mark has no calculated overtime, leaving it to be flagged', function () {
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(6, 0));

    $workday = calculatedWorkday($employee, $date);

    expect($workday->status)->toBe(WorkdayStatus::Incomplete)
        ->and($workday->pre_shift_excess)->toBeNull()
        ->and($workday->post_shift_excess)->toBeNull()
        ->and($workday->calculated_overtime)->toBeNull();
});

test('a shift crossing midnight measures its excess against the next day', function () {
    // A 22:00–06:00 shift. Leaving at 23:30 is well before the shift ends at
    // 06:00 the following morning; comparing clock times alone would have
    // reported seventeen and a half hours of overtime.
    [$employee, $date] = employeeOnShift(
        startTime: '22:00:00',
        endTime: '06:00:00',
        lunchStartTime: null,
        lunchEndTime: null,
    );

    punch($employee, MarkType::In, $date->copy()->setTime(21, 30));
    punch($employee, MarkType::Out, $date->copy()->setTime(23, 30));

    $workday = calculatedWorkday($employee, $date);

    expect($workday->post_shift_excess)->toBe('00:00:00')
        ->and($workday->pre_shift_excess)->toBe('00:30:00')
        ->and($workday->calculated_overtime)->toBe('00:00:00');
});

test('a sub-minute overflow survives to the second', function () {
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(17, 0, 43));

    $workday = calculatedWorkday($employee, $date);

    expect($workday->post_shift_excess)->toBe('00:00:43')
        ->and($workday->calculated_overtime)->toBe('00:00:43');
});

test('extra_time keeps its own meaning and is not replaced by the calculated overtime', function () {
    // Two hours early, on time out. extra_time is span minus scheduled duration
    // and reports two hours; OHC under the default policy reports none. The DT
    // reports read extra_time, so its answer must not move.
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(6, 0));
    punch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    $workday = calculatedWorkday($employee, $date);

    expect($workday->extra_time)->toBe('02:00:00')
        ->and($workday->calculated_overtime)->toBe('00:00:00');
});

test('the excesses of two organizations are judged under their own policies in one pass', function () {
    [$counting, $date] = employeeOnShift();
    countPreShiftExcess($counting);

    [$notCounting] = employeeOnShift();

    foreach ([$counting, $notCounting] as $employee) {
        punch($employee, MarkType::In, $date->copy()->setTime(7, 0));
        punch($employee, MarkType::Out, $date->copy()->setTime(17, 0));
    }

    app(WorkdayCalculator::class)->calculateDate($date);

    $workdays = Workday::withoutGlobalScopes()->get()->keyBy('user_id');

    expect($workdays[$counting->id]->calculated_overtime)->toBe('01:00:00')
        ->and($workdays[$notCounting->id]->calculated_overtime)->toBe('00:00:00')
        ->and($workdays[$notCounting->id]->pre_shift_excess)->toBe('01:00:00');
});

test('recalculateWorkday recomputes the excesses after a mark is corrected', function () {
    [$employee, $date] = employeeOnShift();

    punch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    $markOut = punch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    $workday = calculatedWorkday($employee, $date);
    expect($workday->calculated_overtime)->toBe('00:00:00');

    $markOut->update(['date_time' => $date->copy()->setTime(19, 15)]);

    app(WorkdayCalculator::class)->recalculateWorkday($workday);

    expect($workday->fresh()->post_shift_excess)->toBe('02:15:00')
        ->and($workday->fresh()->calculated_overtime)->toBe('02:15:00');
});
