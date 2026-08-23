<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\WorkdayStatus;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use App\Models\Workday;
use App\Services\WorkdayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

/**
 * An organization with an admin, an employee, and a Monday 08:00–17:00 shift
 * assigned to that employee. Returns the pieces the recalculation tests wire a
 * leave and its workday against.
 *
 * @return array{0: User, 1: User, 2: Carbon}
 */
function leaveOnShift(): array
{
    $organization = Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'vacation_days' => 15,
    ]);

    $date = Carbon::parse('next monday')->startOfDay();

    $shift = Shift::factory()->create(['organization_id' => $organization->id]);
    ShiftDay::factory()->create([
        'shift_id' => $shift->id,
        // ShiftDay weekdays are 0=Monday … 6=Sunday.
        'weekday' => (int) $date->format('N') - 1,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'is_free' => false,
    ]);
    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
        'start_date' => $date->copy()->subWeek()->toDateString(),
        'end_date' => null,
    ]);

    return [$admin, $employee, $date];
}

// The new app has no ON_LEAVE workday status: an approved leave justifies the
// day, so the WorkdayCalculator renders it as Justified. These tests verify the
// full request → approval → recalculation chain that the leave workflow relies
// on, running the calculator to confirm the workday outcome rather than only
// asserting that the recalculation event was dispatched.

test('approving a leave justifies the affected workday', function () {
    [$admin, $employee, $date] = leaveOnShift();

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
        'start_date' => $date->toDateString(),
        'end_date' => $date->toDateString(),
        'business_days_requested' => 1,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('leaves.approve', $leave))
        ->assertRedirect();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Approved);

    app(WorkdayCalculator::class)->calculateDate($date);

    $workday = Workday::withoutGlobalScopes()->where('user_id', $employee->id)->firstOrFail();

    expect($workday->status)->toBe(WorkdayStatus::Justified)
        ->and($workday->leave_id)->toBe($leave->id);
});

test('rejecting a previously approved leave returns the workday to its original status', function () {
    [$admin, $employee, $date] = leaveOnShift();

    $leave = Leave::factory()->approved()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
        'start_date' => $date->toDateString(),
        'end_date' => $date->toDateString(),
        'business_days_requested' => 1,
        'created_by' => $admin->id,
    ]);

    // While approved the day is justified.
    $calculator = app(WorkdayCalculator::class);
    $calculator->calculateDate($date);

    $workday = Workday::withoutGlobalScopes()->where('user_id', $employee->id)->firstOrFail();
    expect($workday->status)->toBe(WorkdayStatus::Justified);

    $this->actingAs($admin)
        ->post(route('leaves.reject', $leave), ['reason' => 'No longer needed.'])
        ->assertRedirect();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Rejected);

    // Recomputing the same day now that the leave is gone reverts it to the
    // shift-derived status: a scheduled shift with no marks is an absence.
    $calculator->recalculateWorkday($workday);

    expect($workday->refresh()->status)->toBe(WorkdayStatus::Absent)
        ->and($workday->leave_id)->toBeNull();
});

test('approving a half-day vacation decrements the balance by half a day', function () {
    [$admin, $employee, $date] = leaveOnShift();

    $leave = Leave::factory()->pending()->halfDay()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => $date->toDateString(),
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('leaves.approve', $leave))
        ->assertRedirect();

    // 15 available less the 0.5 half-day request.
    expect($employee->refresh()->vacation_days)->toEqual(14.5);
});

test('a multi-day vacation decrements the balance by every requested business day', function () {
    [$admin, $employee, $date] = leaveOnShift();

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => $date->toDateString(),
        'end_date' => $date->copy()->addDays(4)->toDateString(),
        'business_days_requested' => 5,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('leaves.approve', $leave))
        ->assertRedirect();

    expect($employee->refresh()->vacation_days)->toEqual(10.0);
});
