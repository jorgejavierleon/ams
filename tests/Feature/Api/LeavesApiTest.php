<?php

use App\Enums\LeaveHalfDayType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\LeaveRequestSubmitted;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function mobileLeaveEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
    ]);
}

// --- Authentication and authorization ---

test('an unauthenticated request for leaves returns 401', function () {
    $this->getJson('/api/v1/me/leaves')->assertUnauthorized();
});

test('an employee without ViewOwn:Leave is forbidden', function () {
    $employee = User::factory()->create(); // no role, no permissions
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/leaves')->assertForbidden();
});

test('an unauthenticated cancel request returns 401', function () {
    $employee = mobileLeaveEmployee();
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);

    $this->deleteJson("/api/v1/me/leaves/{$leave->id}")->assertUnauthorized();
});

test('an employee without CancelOwn:Leave is forbidden from cancelling', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]); // no role, no permissions
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $this->deleteJson("/api/v1/me/leaves/{$leave->id}")->assertForbidden();
});

// --- Listing (#1, #2, #4, #5, #6) ---

test('only the authenticated employee own leaves are returned, newest start_date first', function () {
    $employee = mobileLeaveEmployee();
    $other = mobileLeaveEmployee($employee->organization);

    $older = Leave::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-01',
    ]);
    $newer = Leave::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-08-15',
        'end_date' => '2026-08-15',
    ]);
    Leave::factory()->create([
        'organization_id' => $other->organization_id,
        'user_id' => $other->id,
    ]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/leaves')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.id'))->toBe($newer->id)
        ->and($response->json('data.1.id'))->toBe($older->id);
});

test('an employee with no leaves gets an empty array with vacationBalance still present', function () {
    $employee = mobileLeaveEmployee();
    $employee->update(['vacation_days' => 10, 'additional_vacation_days' => 0]);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/leaves')->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->json('vacationBalance'))->toEqual([
            'used' => 0.0,
            'available' => 10.0,
            'total' => 10.0,
        ]);
});

test('each entry carries the fields the mobile Mis solicitudes screen needs', function () {
    $employee = mobileLeaveEmployee();
    $leave = Leave::factory()->approved()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-14',
    ]);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/leaves')->assertOk();

    expect($response->json('data.0'))->toMatchArray([
        'id' => $leave->id,
        'type_label' => $leave->type->label(),
        'status' => 'approved',
        'status_label' => $leave->status->label(),
        'status_badge' => 'success',
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-14',
        'approver_note' => null,
    ]);
});

test('status_badge mirrors LeaveStatus::badge for each status', function () {
    $employee = mobileLeaveEmployee();
    Leave::factory()->pending()->create(['organization_id' => $employee->organization_id, 'user_id' => $employee->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-01']);
    Leave::factory()->approved()->create(['organization_id' => $employee->organization_id, 'user_id' => $employee->id, 'start_date' => '2026-08-02', 'end_date' => '2026-08-02']);
    Leave::factory()->create(['organization_id' => $employee->organization_id, 'user_id' => $employee->id, 'status' => LeaveStatus::Rejected, 'start_date' => '2026-08-03', 'end_date' => '2026-08-03', 'rejection_reason' => 'Cupo mensual excedido']);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/leaves')->assertOk();

    $badges = collect($response->json('data'))->pluck('status_badge', 'status');
    expect($badges->get('pending'))->toBe('warning')
        ->and($badges->get('approved'))->toBe('success')
        ->and($badges->get('rejected'))->toBe('destructive');

    $rejected = collect($response->json('data'))->firstWhere('status', 'rejected');
    expect($rejected['approver_note'])->toBe('Cupo mensual excedido');
});

test('vacationBalance sums only approved vacation leaves', function () {
    $employee = mobileLeaveEmployee();
    $employee->update(['vacation_days' => 10, 'additional_vacation_days' => 2]);

    Leave::factory()->approved()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'business_days_requested' => 3,
    ]);
    // Pending vacation and approved non-vacation leaves must not count as used.
    Leave::factory()->pending()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'business_days_requested' => 5,
    ]);
    Leave::factory()->approved()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Unpaid,
        'business_days_requested' => 4,
    ]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/leaves')->assertOk();

    expect($response->json('vacationBalance'))->toEqual([
        'used' => 3.0,
        'available' => 12.0,
        'total' => 15.0,
    ]);
});

// --- Cancel (#7, #8) ---

test('the owning employee can cancel their own pending leave', function () {
    $employee = mobileLeaveEmployee();
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $this->deleteJson("/api/v1/me/leaves/{$leave->id}")->assertNoContent();

    expect(Leave::find($leave->id))->toBeNull();
});

test('cancelling an approved leave is forbidden', function () {
    $employee = mobileLeaveEmployee();
    $leave = Leave::factory()->approved()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $this->deleteJson("/api/v1/me/leaves/{$leave->id}")->assertForbidden();

    expect(Leave::find($leave->id))->not->toBeNull();
});

test('cancelling another employee leave is forbidden', function () {
    $employee = mobileLeaveEmployee();
    $other = mobileLeaveEmployee($employee->organization);
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $other->organization_id,
        'user_id' => $other->id,
    ]);
    Sanctum::actingAs($employee);

    $this->deleteJson("/api/v1/me/leaves/{$leave->id}")->assertForbidden();

    expect(Leave::find($leave->id))->not->toBeNull();
});

// --- Options (#1, #2) ---

test('an unauthenticated options request returns 401', function () {
    $this->getJson('/api/v1/me/leaves/options')->assertUnauthorized();
});

test('an employee without RequestOwn:Leave is forbidden from options', function () {
    $employee = User::factory()->create(); // no role, no permissions
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/leaves/options')->assertForbidden();
});

test('options returns the self-service types and never includes Medical', function () {
    $employee = mobileLeaveEmployee();
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/leaves/options')->assertOk();

    expect($response->json('data'))->toEqual(LeaveType::selfServiceOptions())
        ->and(collect($response->json('data'))->pluck('value'))->not->toContain(LeaveType::Medical->value);
});

test('options bundles the half-day types alongside the leave types', function () {
    $employee = mobileLeaveEmployee();
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/leaves/options')->assertOk();

    expect($response->json('halfDayTypes'))->toEqual(LeaveHalfDayType::options());
});

// --- Business days (#3) ---

test('an unauthenticated business-days request returns 401', function () {
    $this->getJson('/api/v1/me/leaves/business-days?start_date=2026-08-24&end_date=2026-08-28')
        ->assertUnauthorized();
});

test('an employee without RequestOwn:Leave is forbidden from business-days', function () {
    $employee = User::factory()->create(); // no role, no permissions
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/leaves/business-days?start_date=2026-08-24&end_date=2026-08-28')
        ->assertForbidden();
});

test('business-days computes the working days for the authenticated employee via BusinessDaysCalculator', function () {
    $employee = mobileLeaveEmployee();
    Sanctum::actingAs($employee);

    // 2026-08-24 is a Monday; through 2026-08-28 (Friday) is a full 5-day work week.
    $response = $this->getJson('/api/v1/me/leaves/business-days?start_date=2026-08-24&end_date=2026-08-28')
        ->assertOk();

    expect($response->json('business_days'))->toEqual(5.0);
});

test('business-days requires start_date and end_date', function () {
    $employee = mobileLeaveEmployee();
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/leaves/business-days')->assertUnprocessable();
});

// --- Store (#4, #5, #6, #7) ---

test('an unauthenticated store request returns 401', function () {
    $this->postJson('/api/v1/me/leaves', [
        'type' => LeaveType::Vacation->value,
        'start_date' => '2026-08-24',
        'end_date' => '2026-08-24',
    ])->assertUnauthorized();
});

test('an employee without RequestOwn:Leave is forbidden from creating a leave', function () {
    $employee = User::factory()->create(); // no role, no permissions
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/me/leaves', [
        'type' => LeaveType::Vacation->value,
        'start_date' => '2026-08-24',
        'end_date' => '2026-08-24',
    ])->assertForbidden();
});

test('a full-day leave request is created with the server-computed business days', function () {
    Notification::fake();
    $employee = mobileLeaveEmployee();
    Sanctum::actingAs($employee);

    // Monday through Friday: a full 5-day work week.
    $response = $this->postJson('/api/v1/me/leaves', [
        'type' => LeaveType::Vacation->value,
        'start_date' => '2026-08-24',
        'end_date' => '2026-08-28',
        'notes' => 'Vacaciones familiares',
    ])->assertCreated();

    $leave = Leave::findOrFail($response->json('data.id'));
    expect($leave->user_id)->toBe($employee->id)
        ->and($leave->organization_id)->toBe($employee->organization_id)
        ->and($leave->type)->toBe(LeaveType::Vacation)
        ->and($leave->status)->toBe(LeaveStatus::Pending)
        ->and($leave->half_day)->toBeFalse()
        ->and($leave->half_day_type)->toBeNull()
        ->and((float) $leave->business_days_requested)->toBe(5.0)
        ->and($leave->notes)->toBe('Vacaciones familiares');
});

test('a half-day leave is confined to a single day and always stores 0.5 business days', function () {
    Notification::fake();
    $employee = mobileLeaveEmployee();
    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/v1/me/leaves', [
        'type' => LeaveType::Vacation->value,
        'start_date' => '2026-08-24',
        'end_date' => '2026-08-24',
        'half_day' => true,
        'half_day_type' => LeaveHalfDayType::Morning->value,
    ])->assertCreated();

    $leave = Leave::findOrFail($response->json('data.id'));
    expect($leave->half_day)->toBeTrue()
        ->and($leave->half_day_type)->toBe(LeaveHalfDayType::Morning)
        ->and((float) $leave->business_days_requested)->toBe(0.5);
});

test('a half-day request spanning more than one day is rejected', function () {
    $employee = mobileLeaveEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/me/leaves', [
        'type' => LeaveType::Vacation->value,
        'start_date' => '2026-08-24',
        'end_date' => '2026-08-25',
        'half_day' => true,
        'half_day_type' => LeaveHalfDayType::Morning->value,
    ])->assertUnprocessable()->assertJsonValidationErrors('end_date');
});

test('requesting Medical leave through self-service is refused', function () {
    $employee = mobileLeaveEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/me/leaves', [
        'type' => LeaveType::Medical->value,
        'start_date' => '2026-08-24',
        'end_date' => '2026-08-24',
    ])->assertUnprocessable()->assertJsonValidationErrors('type');
});

test('submitting a leave notifies the approvers LeaveApprovers resolves', function () {
    Notification::fake();
    $employee = mobileLeaveEmployee();
    $admin = User::factory()->create(['organization_id' => $employee->organization_id]);
    $admin->assignRole('admin');
    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/v1/me/leaves', [
        'type' => LeaveType::Vacation->value,
        'start_date' => '2026-08-24',
        'end_date' => '2026-08-24',
    ])->assertCreated();

    $leave = Leave::findOrFail($response->json('data.id'));

    Notification::assertSentTo(
        $admin,
        LeaveRequestSubmitted::class,
        fn (LeaveRequestSubmitted $notification): bool => $notification->leave->is($leave),
    );
});
