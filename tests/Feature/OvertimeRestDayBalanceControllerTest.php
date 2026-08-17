<?php

use App\Models\Organization;
use App\Models\OvertimeRestDayBalance;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function restDayBalanceAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function restDayBalanceEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $employee->assignRole('employee');

    return $employee;
}

// --- Access control ---

test('an employee without Manage:OvertimeAuthorization cannot reach the HR balance list', function () {
    $employee = restDayBalanceEmployee();

    $this->actingAs($employee)
        ->get(route('overtime.rest-day-balances.index'))
        ->assertForbidden();
});

test('an admin holding Manage:OvertimeAuthorization can reach the HR balance list', function () {
    $admin = restDayBalanceAdmin();

    $this->actingAs($admin)
        ->get(route('overtime.rest-day-balances.index'))
        ->assertOk();
});

// --- AC #6: visible in Spanish, to the right audience ---

test('HR sees every employee balance for its organization with the employee name', function () {
    $admin = restDayBalanceAdmin();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Ana Soto']);

    OvertimeRestDayBalance::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'accrued_hours' => '02:00:00',
        'rest_hours' => '03:00:00',
    ]);

    $this->actingAs($admin)
        ->get(route('overtime.rest-day-balances.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('overtime/rest-day-balances/index')
                ->has('balances.data', 1)
                ->where('balances.data.0.employee', 'Ana Soto')
                ->where('balances.data.0.rest_hours', '03:00:00')
                ->where('balances.data.0.status.value', 'active'),
        );
});

test('a tenant never sees another tenant rest-day balances', function () {
    $admin = restDayBalanceAdmin();
    OvertimeRestDayBalance::factory()->create(['organization_id' => $admin->organization_id]);
    OvertimeRestDayBalance::factory()->create();

    $this->actingAs($admin)
        ->get(route('overtime.rest-day-balances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('balances.data', 1));
});

test('an employee sees only their own rest-day balance', function () {
    $organization = Organization::factory()->create();
    $employee = restDayBalanceEmployee($organization);
    $other = User::factory()->create(['organization_id' => $organization->id]);

    OvertimeRestDayBalance::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'accrued_hours' => '01:00:00',
        'rest_hours' => '01:30:00',
    ]);
    OvertimeRestDayBalance::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($employee)
        ->get(route('my.overtime-rest-day-balance.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('my/overtime-rest-day-balance/index')
                ->has('lines', 1)
                ->where('lines.0.rest_hours', '01:30:00')
                ->where('available', '01:30:00'),
        );
});

// --- Consumption ---

test('HR can register a consumption against an employee balance', function () {
    $admin = restDayBalanceAdmin();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);

    $balance = OvertimeRestDayBalance::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'rest_hours' => '03:00:00',
    ]);

    $this->actingAs($admin)
        ->post(route('overtime.rest-day-balances.consume'), [
            'user_id' => $employee->id,
            'hours' => '01:00',
            'consumed_on' => '2026-08-20',
            'note' => 'Día libre por horas extra.',
        ])
        ->assertRedirect();

    expect((string) $balance->fresh()->remaining())->toBe('02:00:00');
});

test('registering a consumption beyond the available balance fails validation and writes nothing', function () {
    $admin = restDayBalanceAdmin();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);

    $balance = OvertimeRestDayBalance::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'rest_hours' => '01:00:00',
    ]);

    $this->actingAs($admin)
        ->post(route('overtime.rest-day-balances.consume'), [
            'user_id' => $employee->id,
            'hours' => '02:00',
            'consumed_on' => '2026-08-20',
        ])
        ->assertSessionHasErrors('hours');

    expect((string) $balance->fresh()->remaining())->toBe('01:00:00');
});

test('an admin cannot register a consumption for an employee of another organization', function () {
    $admin = restDayBalanceAdmin();
    $foreignEmployee = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('overtime.rest-day-balances.consume'), [
            'user_id' => $foreignEmployee->id,
            'hours' => '01:00',
            'consumed_on' => '2026-08-20',
        ])
        ->assertSessionHasErrors('user_id');
});
