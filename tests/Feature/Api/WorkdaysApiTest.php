<?php

use App\Enums\LeaveType;
use App\Enums\WorkdayStatus;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function workdaysEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
    ]);
}

// --- Authentication and authorization (#1) ---

test('an unauthenticated request for workday history returns 401', function () {
    $this->getJson('/api/v1/me/workdays')->assertUnauthorized();
});

test('an employee without ViewOwn:Workday is forbidden', function () {
    $employee = User::factory()->create(); // no role, no permissions
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/workdays')->assertForbidden();
});

test('only the authenticated employee own workdays are returned', function () {
    $employee = workdaysEmployee();
    $other = workdaysEmployee($employee->organization);

    $mine = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->toDateString(),
    ]);
    Workday::factory()->create([
        'organization_id' => $other->organization_id,
        'user_id' => $other->id,
        'date' => now()->toDateString(),
    ]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/workdays')->assertOk();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.date'))->toBe($mine->date->toDateString());
});

// --- Range defaults and swapping (#2) ---

test('the range defaults to the current month', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    $inMonth = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => Carbon::today()->startOfMonth()->toDateString(),
    ]);
    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => Carbon::today()->subMonthsNoOverflow(2)->toDateString(),
    ]);

    $response = $this->getJson('/api/v1/me/workdays')->assertOk();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.date'))->toBe($inMonth->date->toDateString());
});

test('an explicit from and to range is honoured', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    $older = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => '2026-01-15',
    ]);
    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->toDateString(),
    ]);

    $response = $this->getJson('/api/v1/me/workdays?from=2026-01-01&to=2026-01-31')->assertOk();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.date'))->toBe($older->date->toDateString());
});

test('a reversed from and to range is swapped rather than returning nothing', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => '2026-01-15',
    ]);

    $response = $this->getJson('/api/v1/me/workdays?from=2026-01-31&to=2026-01-01')->assertOk();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.date'))->toBe($workday->date->toDateString());
});

test('a range with no workdays returns an empty array', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/workdays?from=2020-01-01&to=2020-01-31')->assertOk();

    expect($response->json())->toBe([]);
});

// --- Shape and ordering (#3, #5) ---

test('each workday carries the figures and the server badge tone, newest first', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => Carbon::today()->startOfMonth()->toDateString(),
        'status' => WorkdayStatus::Regular,
        'worked_time' => '08:03:00',
        'extra_time' => '00:00:00',
        'missing_time' => '00:00:00',
    ]);
    $newest = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => Carbon::today()->toDateString(),
        'status' => WorkdayStatus::Incomplete,
    ]);

    $response = $this->getJson('/api/v1/me/workdays')->assertOk();

    expect($response->json('0.date'))->toBe($newest->date->toDateString())
        ->and($response->json('0.status'))->toBe('incomplete')
        ->and($response->json('0.status_badge'))->toBe('warning')
        ->and($response->json('1.status'))->toBe('regular')
        ->and($response->json('1.status_badge'))->toBe('success')
        ->and($response->json('1.worked_time'))->toBe('08:03')
        ->and($response->json('1.extra_time'))->toBe('00:00')
        ->and($response->json('1.missing_time'))->toBe('00:00');
});

// --- Leave annotation (#4) ---

test('a day covered by an approved leave carries the leave type instead of the figures', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    $leave = Leave::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Medical,
    ]);
    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->toDateString(),
        'leave_id' => $leave->id,
        'status' => WorkdayStatus::Justified,
        'worked_time' => '00:00:00',
    ]);

    $response = $this->getJson('/api/v1/me/workdays')->assertOk();

    expect($response->json('0.leave_type_label'))->toBe(LeaveType::Medical->label())
        ->and($response->json('0.worked_time'))->toBeNull()
        ->and($response->json('0.extra_time'))->toBeNull()
        ->and($response->json('0.missing_time'))->toBeNull();
});

// --- No 5-year cap (#7) ---

test('a range spanning years is not truncated', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->subYears(4)->toDateString(),
    ]);
    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->toDateString(),
    ]);

    $response = $this->getJson(
        '/api/v1/me/workdays?from='.now()->subYears(5)->toDateString().'&to='.now()->toDateString()
    )->assertOk();

    expect($response->json())->toHaveCount(2);
});
