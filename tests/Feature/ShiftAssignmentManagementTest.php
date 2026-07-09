<?php

use App\Events\WorkdaysRecalculationNeeded;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function assignmentAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function assignmentEmployee(Organization $organization): User
{
    return User::factory()->employee()->create(['organization_id' => $organization->id]);
}

function assignmentShift(Organization $organization): Shift
{
    return Shift::factory()->create(['organization_id' => $organization->id]);
}

// --- Show page ---

test('the employee show page includes shift assignments as a loaded prop', function () {
    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);
    $shift = assignmentShift($organization);

    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page
            ->component('employees/show')
            // A regular prop (not deferred): the assignments must be present on
            // the initial render so the stateful add form never unmounts.
            ->has('shifts.assignments', 1)
            ->where('shifts.assignments.0.shift', $shift->name)
            ->where('shifts.assignments.0.status', 'current')
            ->has('shifts.shiftOptions'));
});

test('assignment status reflects the date range relative to today', function () {
    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);
    $shift = assignmentShift($organization);

    // Ended: finished last month.
    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
    ]);

    // Upcoming: starts next month.
    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => now()->addMonth()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page
            // Ordered by start_date desc: upcoming first, then ended.
            ->where('shifts.assignments.0.status', 'upcoming')
            ->where('shifts.assignments.1.status', 'ended'));
});

// --- Access control ---

test('unauthenticated users cannot create assignments', function () {
    $organization = Organization::factory()->create();
    $employee = assignmentEmployee($organization);

    $this->post(route('employees.shift-assignments.store', $employee), [])
        ->assertRedirect(route('login'));
});

test('non-admin users are denied', function () {
    $organization = Organization::factory()->create();
    $employee = assignmentEmployee($organization);

    $this->actingAs(assignmentEmployee($organization))
        ->post(route('employees.shift-assignments.store', $employee), [])
        ->assertForbidden();
});

// --- Create ---

test('admin can assign a shift to an employee', function () {
    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);
    $shift = assignmentShift($organization);

    $this->actingAs($admin)
        ->post(route('employees.shift-assignments.store', $employee), [
            'shift_id' => $shift->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => null,
        ])
        ->assertRedirect();

    $assignment = ShiftAssignment::first();

    expect($assignment)->not->toBeNull()
        ->and($assignment->user_id)->toBe($employee->id)
        ->and($assignment->shift_id)->toBe($shift->id)
        ->and($assignment->end_date)->toBeNull()
        ->and($assignment->is_permanent)->toBeTrue()
        ->and($assignment->organization_id)->toBe($organization->id);
});

test('assigning a shift with an end date marks it non-permanent', function () {
    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);
    $shift = assignmentShift($organization);

    $this->actingAs($admin)
        ->post(route('employees.shift-assignments.store', $employee), [
            'shift_id' => $shift->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ])
        ->assertRedirect();

    expect(ShiftAssignment::first()->is_permanent)->toBeFalse();
});

// --- Overlap validation ---

test('overlapping assignments for the same employee are rejected', function () {
    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);
    $shift = assignmentShift($organization);

    // An open-ended assignment that started two weeks ago.
    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => now()->subWeeks(2)->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('employees.shift-assignments.store', $employee), [
            'shift_id' => $shift->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => null,
        ])
        ->assertSessionHasErrors('start_date');

    expect(ShiftAssignment::count())->toBe(1);
});

test('a new assignment after an ended one is allowed', function () {
    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);
    $shift = assignmentShift($organization);

    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->post(route('employees.shift-assignments.store', $employee), [
            'shift_id' => $shift->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => null,
        ])
        ->assertSessionHasNoErrors();

    expect(ShiftAssignment::count())->toBe(2);
});

test('another employee can hold an overlapping range', function () {
    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);
    $other = assignmentEmployee($organization);
    $shift = assignmentShift($organization);

    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $other->id,
        'shift_id' => $shift->id,
        'start_date' => now()->subWeeks(2)->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('employees.shift-assignments.store', $employee), [
            'shift_id' => $shift->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => null,
        ])
        ->assertSessionHasNoErrors();

    expect(ShiftAssignment::where('user_id', $employee->id)->count())->toBe(1);
});

// --- End ---

test('admin can end an active assignment', function () {
    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);

    $assignment = ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'shift_id' => assignmentShift($organization)->id,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('shift-assignments.end', $assignment))
        ->assertRedirect();

    $assignment->refresh();

    expect($assignment->end_date->toDateString())->toBe(now()->toDateString())
        ->and($assignment->is_permanent)->toBeFalse();
});

// --- Delete ---

test('admin can delete an assignment', function () {
    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);

    $assignment = ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'shift_id' => assignmentShift($organization)->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('shift-assignments.destroy', $assignment))
        ->assertRedirect();

    expect($assignment->fresh()->trashed())->toBeTrue();
});

// --- Observer ---

test('creating an assignment dispatches workday recalculation', function () {
    Event::fake([WorkdaysRecalculationNeeded::class]);

    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);
    $shift = assignmentShift($organization);

    $this->actingAs($admin)
        ->post(route('employees.shift-assignments.store', $employee), [
            'shift_id' => $shift->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => null,
        ]);

    Event::assertDispatched(WorkdaysRecalculationNeeded::class, function (WorkdaysRecalculationNeeded $event) use ($employee) {
        return $event->userIds->contains($employee->id);
    });
});

test('a future-dated assignment does not trigger recalculation', function () {
    Event::fake([WorkdaysRecalculationNeeded::class]);

    $admin = assignmentAdmin();
    $organization = $admin->organization;
    $employee = assignmentEmployee($organization);
    $shift = assignmentShift($organization);

    $this->actingAs($admin)
        ->post(route('employees.shift-assignments.store', $employee), [
            'shift_id' => $shift->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => null,
        ]);

    Event::assertNotDispatched(WorkdaysRecalculationNeeded::class);
});
