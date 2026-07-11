<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Events\WorkdaysRecalculationNeeded;
use App\Models\Holiday;
use App\Models\Leave;
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

function leaveAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function leaveEmployee(Organization $organization, array $attributes = []): User
{
    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        ...$attributes,
    ]);
}

// --- Access control ---

test('unauthenticated users cannot list leaves', function () {
    $this->get(route('leaves.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(leaveEmployee($organization))
        ->get(route('leaves.index'))
        ->assertForbidden();
});

// --- Index ---

test('admin sees the leaves list scoped to their organization', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'created_by' => $admin->id,
    ]);

    // A leave in another organization must not leak in.
    $otherOrg = Organization::factory()->create();
    Leave::factory()->create([
        'organization_id' => $otherOrg->id,
        'user_id' => leaveEmployee($otherOrg)->id,
        'created_by' => leaveAdmin($otherOrg)->id,
    ]);

    $this->actingAs($admin)
        ->get(route('leaves.index'))
        ->assertInertia(fn ($page) => $page
            ->component('leaves/index')
            ->has('leaves.data', 1)
            ->where('leaves.data.0.employee', $employee->name));
});

test('the index exposes detail fields for the leave view panel', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'created_by' => $admin->id,
        'type' => LeaveType::Medical,
        'medical_leave_number' => '12345',
        'medical_leave_doctor' => 'Dr. House',
        'notes' => 'Bed rest for a week.',
    ]);

    $this->actingAs($admin)
        ->get(route('leaves.index'))
        ->assertInertia(fn ($page) => $page
            ->where('leaves.data.0.medical_leave_number', '12345')
            ->where('leaves.data.0.medical_leave_doctor', 'Dr. House')
            ->where('leaves.data.0.notes', 'Bed rest for a week.')
            ->whereNot('leaves.data.0.created_at', null));
});

test('the status filter narrows the list', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'created_by' => $admin->id,
    ]);
    Leave::factory()->approved()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('leaves.index', ['status' => 'approved']))
        ->assertInertia(fn ($page) => $page
            ->has('leaves.data', 1)
            ->where('leaves.data.0.status', 'approved'));
});

// --- Create ---

test('admin can create a pending leave request', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    $this->actingAs($admin)
        ->post(route('leaves.store'), [
            'user_id' => $employee->id,
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'half_day' => false,
            'business_days_requested' => 3,
        ])
        ->assertRedirect(route('leaves.index'));

    $leave = Leave::first();

    expect($leave)->not->toBeNull()
        ->and($leave->user_id)->toBe($employee->id)
        ->and($leave->status)->toBe(LeaveStatus::Pending)
        ->and($leave->created_by)->toBe($admin->id)
        ->and($leave->organization_id)->toBe($organization->id)
        ->and((float) $leave->business_days_requested)->toBe(3.0);
});

test('a half-day leave is forced to a single day worth half a business day', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);
    $day = now()->addDay()->toDateString();

    $this->actingAs($admin)
        ->post(route('leaves.store'), [
            'user_id' => $employee->id,
            'type' => LeaveType::Vacation->value,
            'start_date' => $day,
            'end_date' => $day,
            'half_day' => true,
            'half_day_type' => 'morning',
            'business_days_requested' => 3,
        ])
        ->assertRedirect(route('leaves.index'));

    $leave = Leave::first();

    expect($leave->half_day)->toBeTrue()
        ->and((float) $leave->business_days_requested)->toBe(0.5);
});

test('a half-day leave spanning multiple days is rejected', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    $this->actingAs($admin)
        ->post(route('leaves.store'), [
            'user_id' => $employee->id,
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'half_day' => true,
            'half_day_type' => 'morning',
            'business_days_requested' => 0.5,
        ])
        ->assertSessionHasErrors('end_date');

    expect(Leave::count())->toBe(0);
});

test('creating a medical leave auto-approves it and dispatches recalculation', function () {
    Event::fake([WorkdaysRecalculationNeeded::class]);

    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    $this->actingAs($admin)
        ->post(route('leaves.store'), [
            'user_id' => $employee->id,
            'type' => LeaveType::Medical->value,
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
            'half_day' => false,
            'business_days_requested' => 3,
            'medical_leave_number' => '12345',
        ])
        ->assertRedirect(route('leaves.index'));

    expect(Leave::first()->status)->toBe(LeaveStatus::Approved);

    Event::assertDispatched(WorkdaysRecalculationNeeded::class);
});

// --- Approve / reject ---

test('approving a leave transitions it and dispatches recalculation', function () {
    Event::fake([WorkdaysRecalculationNeeded::class]);

    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('leaves.approve', $leave))
        ->assertRedirect();

    $leave->refresh();

    expect($leave->status)->toBe(LeaveStatus::Approved)
        ->and($leave->approved_by)->toBe($admin->id);

    Event::assertDispatched(WorkdaysRecalculationNeeded::class);
});

test('approving a vacation deducts the requested days from the balance', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization, ['vacation_days' => 15]);

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'business_days_requested' => 5,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('leaves.approve', $leave))
        ->assertRedirect();

    expect($employee->refresh()->vacation_days)->toEqual(10.0);
});

test('rejecting a previously approved vacation refunds the balance', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization, ['vacation_days' => 10]);

    $leave = Leave::factory()->approved()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'business_days_requested' => 5,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('leaves.reject', $leave))
        ->assertRedirect();

    $leave->refresh();

    expect($leave->status)->toBe(LeaveStatus::Rejected)
        ->and($leave->approved_by)->toBeNull()
        ->and($employee->refresh()->vacation_days)->toEqual(15.0);
});

test('medical leaves cannot be rejected', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    $leave = Leave::factory()->medical()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('leaves.reject', $leave))
        ->assertForbidden();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Approved);
});

// --- Vacation balance on the employee page ---

test('the employee show page exposes the vacation balance', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    // 10 days remaining + 2 additional; 5 already used on an approved vacation.
    $employee = leaveEmployee($organization, [
        'vacation_days' => 10,
        'additional_vacation_days' => 2,
    ]);

    Leave::factory()->approved()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'business_days_requested' => 5,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        // Inertia serializes whole floats without a decimal point, so the JSON
        // payload carries these day counts as integers.
        ->assertInertia(fn ($page) => $page
            ->where('vacationBalance.used', 5)
            ->where('vacationBalance.available', 12)
            ->where('vacationBalance.total', 17));
});

// --- Business days estimate ---

test('the business days endpoint counts shift working days minus holidays', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    // A default shift works Monday–Friday (Saturday/Sunday free).
    $shift = Shift::factory()->create(['organization_id' => $organization->id]);

    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'shift_id' => $shift->id,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);

    // A holiday lands on a Wednesday inside the range.
    Holiday::factory()->create([
        'organization_id' => $organization->id,
        'date' => '2026-03-04',
    ]);

    $response = $this->actingAs($admin)->getJson(route('leaves.business-days', [
        'employee' => $employee->id,
        'start_date' => '2026-03-02', // Monday
        'end_date' => '2026-03-06',   // Friday
    ]));

    $response->assertOk();

    // 5 weekdays minus the mid-week holiday.
    expect($response->json('business_days'))->toEqual(4);
});

test('the business days endpoint falls back to a Monday–Friday week without a shift', function () {
    $admin = leaveAdmin();
    $organization = $admin->organization;
    $employee = leaveEmployee($organization);

    $response = $this->actingAs($admin)->getJson(route('leaves.business-days', [
        'employee' => $employee->id,
        'start_date' => '2026-03-02', // Monday
        'end_date' => '2026-03-08',   // Sunday
    ]));

    $response->assertOk();

    // Monday–Friday counted, the weekend excluded.
    expect($response->json('business_days'))->toEqual(5);
});
