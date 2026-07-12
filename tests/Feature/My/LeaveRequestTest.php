<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seeds the roles and grants the self-service permissions to `employee`.
    $this->seed(RoleSeeder::class);
});

function selfServiceEmployee(?Organization $organization = null, array $attributes = []): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        ...$attributes,
    ]);
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('my.leaves.index'))->assertRedirect(route('login'));
});

test('a user without the self-service permissions is forbidden', function () {
    $user = User::factory()->create(); // no roles, so no permissions

    $this->actingAs($user)
        ->get(route('my.leaves.index'))
        ->assertForbidden();
});

test('viewing does not grant cancelling', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $user->givePermissionTo('ViewOwn:Leave');

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('my.leaves.destroy', $leave))
        ->assertForbidden();

    expect(Leave::find($leave->id))->not->toBeNull();
});

// --- Index ---

test('an employee sees only their own leaves', function () {
    $organization = Organization::factory()->create();
    $employee = selfServiceEmployee($organization);
    $other = selfServiceEmployee($organization);

    Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);
    Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($employee)
        ->get(route('my.leaves.index'))
        ->assertInertia(fn ($page) => $page
            ->component('my/leaves/index')
            ->has('leaves.data', 1)
            ->where('leaves.data.0.status', LeaveStatus::Pending->value));
});

test('the index exposes the employee vacation balance', function () {
    $employee = selfServiceEmployee(null, [
        'vacation_days' => 10,
        'additional_vacation_days' => 2,
    ]);

    Leave::factory()->approved()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'business_days_requested' => 5,
    ]);

    $this->actingAs($employee)
        ->get(route('my.leaves.index'))
        ->assertInertia(fn ($page) => $page
            ->where('vacationBalance.used', 5)
            ->where('vacationBalance.available', 12)
            ->where('vacationBalance.total', 17));
});

// --- Create ---

test('an employee can create a pending leave request for themselves', function () {
    $employee = selfServiceEmployee();

    $this->actingAs($employee)
        ->post(route('my.leaves.store'), [
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'half_day' => false,
            'business_days_requested' => 3,
        ])
        ->assertRedirect(route('my.leaves.index'));

    $leave = Leave::first();

    expect($leave)->not->toBeNull()
        ->and($leave->user_id)->toBe($employee->id)
        ->and($leave->status)->toBe(LeaveStatus::Pending)
        ->and($leave->organization_id)->toBe($employee->organization_id);
});

test('the requester is always the authenticated employee', function () {
    $organization = Organization::factory()->create();
    $employee = selfServiceEmployee($organization);
    $victim = selfServiceEmployee($organization);

    $this->actingAs($employee)
        ->post(route('my.leaves.store'), [
            // A forged user_id must be ignored.
            'user_id' => $victim->id,
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'half_day' => false,
            'business_days_requested' => 2,
        ])
        ->assertRedirect(route('my.leaves.index'));

    expect(Leave::first()->user_id)->toBe($employee->id);
});

test('an employee cannot request a medical leave', function () {
    $employee = selfServiceEmployee();

    $this->actingAs($employee)
        ->post(route('my.leaves.store'), [
            'type' => LeaveType::Medical->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'half_day' => false,
            'business_days_requested' => 2,
        ])
        ->assertSessionHasErrors('type');

    expect(Leave::count())->toBe(0);
});

test('a half-day request is forced to a single day worth half a business day', function () {
    $employee = selfServiceEmployee();
    $day = now()->addDay()->toDateString();

    $this->actingAs($employee)
        ->post(route('my.leaves.store'), [
            'type' => LeaveType::Vacation->value,
            'start_date' => $day,
            'end_date' => $day,
            'half_day' => true,
            'half_day_type' => 'morning',
            'business_days_requested' => 3,
        ])
        ->assertRedirect(route('my.leaves.index'));

    $leave = Leave::first();

    expect($leave->half_day)->toBeTrue()
        ->and((float) $leave->business_days_requested)->toBe(0.5);
});

// --- Cancel (destroy) ---

test('an employee can cancel their own pending request', function () {
    $employee = selfServiceEmployee();

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($employee)
        ->delete(route('my.leaves.destroy', $leave))
        ->assertRedirect();

    expect(Leave::find($leave->id))->toBeNull();
});

test('an employee cannot cancel a request that is no longer pending', function () {
    $employee = selfServiceEmployee();

    $leave = Leave::factory()->approved()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
    ]);

    $this->actingAs($employee)
        ->delete(route('my.leaves.destroy', $leave))
        ->assertForbidden();

    expect(Leave::find($leave->id))->not->toBeNull();
});

test('an employee cannot cancel another employees request', function () {
    $organization = Organization::factory()->create();
    $employee = selfServiceEmployee($organization);
    $other = selfServiceEmployee($organization);

    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($employee)
        ->delete(route('my.leaves.destroy', $leave))
        ->assertForbidden();

    expect(Leave::find($leave->id))->not->toBeNull();
});

// --- Business days estimate ---

test('the business days endpoint estimates for the authenticated employee', function () {
    $employee = selfServiceEmployee();

    $response = $this->actingAs($employee)->getJson(route('my.leaves.business-days', [
        'start_date' => '2026-03-02', // Monday
        'end_date' => '2026-03-08',   // Sunday
    ]));

    $response->assertOk();

    // Monday–Friday counted, the weekend excluded (no shift → Mon–Fri fallback).
    expect($response->json('business_days'))->toEqual(5);
});

test('the self-service permissions are granted to the employee role', function () {
    expect(Permission::whereIn('name', ['RequestOwn:Leave', 'ViewOwn:Leave', 'CancelOwn:Leave'])->count())
        ->toBe(3);
});
