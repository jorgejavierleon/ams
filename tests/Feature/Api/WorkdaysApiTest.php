<?php

use App\Enums\LeaveType;
use App\Enums\MarkType;
use App\Enums\WorkdayStatus;
use App\Models\Leave;
use App\Models\Mark;
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

// --- Day detail (KMO-34) ---

test('an unauthenticated request for a workday detail returns 401', function () {
    $this->getJson('/api/v1/me/workdays/2026-01-15')->assertUnauthorized();
});

test('an employee without ViewOwn:Workday is forbidden from the detail route', function () {
    $employee = User::factory()->create(); // no role, no permissions
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/workdays/2026-01-15')->assertForbidden();
});

test('the workday detail carries the shift window and each punch mark_id', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    $markIn = Mark::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => MarkType::In,
        'date_time' => '2026-01-15 08:02:00',
    ]);
    $markOut = Mark::factory()->out()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => MarkType::Out,
        'date_time' => '2026-01-15 17:05:00',
    ]);

    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => '2026-01-15',
        'shift_start_time' => '08:00:00',
        'shift_end_time' => '17:00:00',
        'mark_in_id' => $markIn->id,
        'mark_out_id' => $markOut->id,
        'status' => WorkdayStatus::Regular,
        'worked_time' => '08:03:00',
        'extra_time' => '00:00:00',
        'missing_time' => '00:00:00',
    ]);

    $response = $this->getJson('/api/v1/me/workdays/2026-01-15')->assertOk();

    expect($response->json('date'))->toBe('2026-01-15')
        ->and($response->json('status'))->toBe('regular')
        ->and($response->json('status_badge'))->toBe('success')
        ->and($response->json('shift_start'))->toBe('08:00:00')
        ->and($response->json('shift_end'))->toBe('17:00:00')
        ->and($response->json('worked_time'))->toBe('08:03')
        ->and($response->json('mark_in'))->toBe(['time' => '08:02:00', 'mark_id' => $markIn->id])
        ->and($response->json('mark_out'))->toBe(['time' => '17:05:00', 'mark_id' => $markOut->id]);
});

test('a workday with no shift assigned has a null shift window', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => '2026-01-15',
        'shift_start_time' => null,
        'shift_end_time' => null,
    ]);

    $response = $this->getJson('/api/v1/me/workdays/2026-01-15')->assertOk();

    expect($response->json('shift_start'))->toBeNull()
        ->and($response->json('shift_end'))->toBeNull();
});

test('a shift crossing midnight carries its raw scheduled times through unmodified', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => '2026-01-15',
        'shift_start_time' => '22:00:00',
        'shift_end_time' => '06:00:00',
    ]);

    $response = $this->getJson('/api/v1/me/workdays/2026-01-15')->assertOk();

    // The resource does not reason about which side of midnight either time
    // falls on — that is the mobile client's job (§6) — so a shift whose
    // scheduled end clock-reads earlier than its start still passes both
    // values through exactly as stored.
    expect($response->json('shift_start'))->toBe('22:00:00')
        ->and($response->json('shift_end'))->toBe('06:00:00');
});

test('a day covered by an approved leave nulls the figures and carries the leave type', function () {
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
        'date' => '2026-01-15',
        'leave_id' => $leave->id,
        'status' => WorkdayStatus::Justified,
        'worked_time' => '00:00:00',
    ]);

    $response = $this->getJson('/api/v1/me/workdays/2026-01-15')->assertOk();

    expect($response->json('leave_type_label'))->toBe(LeaveType::Medical->label())
        ->and($response->json('worked_time'))->toBeNull()
        ->and($response->json('extra_time'))->toBeNull()
        ->and($response->json('missing_time'))->toBeNull();
});

test('a day with only one recorded punch nulls the missing side', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    $markIn = Mark::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => MarkType::In,
        'date_time' => '2026-01-15 08:02:00',
    ]);

    Workday::factory()->incomplete()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => '2026-01-15',
        'mark_in_id' => $markIn->id,
    ]);

    $response = $this->getJson('/api/v1/me/workdays/2026-01-15')->assertOk();

    expect($response->json('mark_in'))->toBe(['time' => '08:02:00', 'mark_id' => $markIn->id])
        ->and($response->json('mark_out'))->toBeNull();
});

test('a date with no computed workday for this employee 404s', function () {
    $employee = workdaysEmployee();
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/workdays/2026-01-15')->assertNotFound();
});

test('another employee workday for the same date is not reachable', function () {
    $employee = workdaysEmployee();
    $other = workdaysEmployee($employee->organization);

    Workday::factory()->create([
        'organization_id' => $other->organization_id,
        'user_id' => $other->id,
        'date' => '2026-01-15',
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/workdays/2026-01-15')->assertNotFound();
});
