<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
