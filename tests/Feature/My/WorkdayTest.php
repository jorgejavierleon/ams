<?php

use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seeds the roles and grants the self-service permissions to `employee`.
    $this->seed(RoleSeeder::class);
});

/**
 * An employee in the given organization who may view their workdays and review
 * their own mark corrections.
 */
function reviewingEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
    ]);
}

/**
 * A pending correction wired to a workday owned by the given employee.
 *
 * @param  array<string, mixed>  $overrides
 */
function pendingCorrection(User $employee, array $overrides = [], ?string $date = null): MarkModification
{
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => $date ?? now()->toDateString(),
    ]);

    return MarkModification::factory()->create(array_merge([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'workday_id' => $workday->id,
        'mark_id' => null,
        'mark_type' => MarkType::In,
    ], $overrides));
}

// --- Access control ---

test('guests cannot view their workdays', function () {
    $this->get(route('my.workdays.index'))->assertRedirect(route('login'));
});

test('a user without the view-own-workday permission is forbidden', function () {
    $user = User::factory()->create(); // no roles, so no permissions

    $this->actingAs($user)
        ->get(route('my.workdays.index'))
        ->assertForbidden();
});

test('the self-service workday permissions are granted to the employee role', function () {
    expect(Permission::whereIn('name', ['ViewOwn:Workday', 'ReviewOwn:MarkModification'])->count())->toBe(2)
        ->and(Role::findByName('employee')->hasAllPermissions(['ViewOwn:Workday', 'ReviewOwn:MarkModification']))->toBeTrue();
});

// --- Listing ---

test('the page lists the employee own workdays and pending corrections', function () {
    $employee = reviewingEmployee();
    $correction = pendingCorrection($employee);

    // Another employee in the same organization: their workday must not leak in.
    $other = reviewingEmployee($employee->organization);
    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($employee)
        ->get(route('my.workdays.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('my/workdays/index')
            ->has('pendingModifications', 1)
            ->where('pendingModifications.0.id', $correction->id)
            ->has('workdays', 1)
            ->where('workdays.0.id', $correction->workday_id));
});

test('the pending corrections count is shared for the nav badge', function () {
    $employee = reviewingEmployee();
    pendingCorrection($employee, date: now()->toDateString());
    pendingCorrection($employee, date: now()->subDay()->toDateString());

    $this->actingAs($employee)
        ->get(route('my.workdays.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.pendingModificationsCount', 2));
});

// --- Detail page ---

test('the employee sees the detail of their own workday with its timeline', function () {
    $employee = reviewingEmployee();
    $correction = pendingCorrection($employee);

    $this->actingAs($employee)
        ->get(route('my.workdays.show', $correction->workday_id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('my/workdays/show')
            ->where('workday.id', $correction->workday_id)
            ->has('workday.mark_in')
            ->has('workday.worked_time')
            ->has('modifications', 1)
            // As the assigned reviewer, the employee may act on it inline.
            ->where('modifications.0.can_review', true));
});

test('the employee cannot view another employee workday detail', function () {
    $employee = reviewingEmployee();
    $intruder = reviewingEmployee($employee->organization);
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($intruder)
        ->get(route('my.workdays.show', $workday->id))
        ->assertForbidden();
});

// --- Reviewing ---

test('an employee approves a correction from their workdays page', function () {
    $employee = reviewingEmployee();
    $correction = pendingCorrection($employee);

    $this->actingAs($employee)
        ->post(route('my.workdays.modifications.approve', [
            'workday' => $correction->workday_id,
            'markModification' => $correction->id,
        ]))
        ->assertRedirect();

    $correction->refresh();

    expect($correction->status)->toBe(MarkModificationStatus::Approved)
        ->and($correction->reviewed_by)->toBe($employee->id)
        ->and($correction->mark_id)->not->toBeNull();

    expect($correction->workday->refresh()->mark_in_id)->toBe($correction->mark_id);
});

test('an employee declines a correction without changing any mark', function () {
    $employee = reviewingEmployee();
    $correction = pendingCorrection($employee);

    $this->actingAs($employee)
        ->post(route('my.workdays.modifications.decline', [
            'workday' => $correction->workday_id,
            'markModification' => $correction->id,
        ]))
        ->assertRedirect();

    expect($correction->refresh()->status)->toBe(MarkModificationStatus::Declined)
        ->and(Mark::count())->toBe(0);
});

test('an employee cannot review another employee correction', function () {
    $employee = reviewingEmployee();
    $intruder = reviewingEmployee($employee->organization);
    $correction = pendingCorrection($employee);

    $this->actingAs($intruder)
        ->post(route('my.workdays.modifications.approve', [
            'workday' => $correction->workday_id,
            'markModification' => $correction->id,
        ]))
        ->assertForbidden();

    expect($correction->refresh()->status)->toBe(MarkModificationStatus::Pending);
});

test('an expired correction cannot be reviewed from the page', function () {
    $employee = reviewingEmployee();
    $correction = pendingCorrection($employee, ['created_at' => now()->subHours(49)]);

    $this->actingAs($employee)
        ->post(route('my.workdays.modifications.approve', [
            'workday' => $correction->workday_id,
            'markModification' => $correction->id,
        ]))
        ->assertForbidden();

    expect($correction->refresh()->status)->toBe(MarkModificationStatus::Pending)
        ->and(Mark::count())->toBe(0);
});
