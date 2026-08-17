<?php

use App\Enums\LeaveType;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\Premise;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Mail::fake();
});

/** Same reasoning as TodayApiTest's own helper: the employee's wall-clock day, not the server's. */
function upcomingToday(): Carbon
{
    return Carbon::now('America/Santiago');
}

/**
 * @param  array<string, mixed>  $premiseAttributes
 */
function upcomingEmployee(?Organization $organization = null, array $premiseAttributes = []): User
{
    $organization ??= Organization::factory()->create();

    $premise = Premise::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Sucursal Ñuñoa',
        ...$premiseAttributes,
    ]);

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'premise_id' => $premise->id,
        'timezone' => 'America/Santiago',
    ]);
}

/**
 * Assigns a Monday–Friday 08:00–17:00 shift (ShiftObserver's own default —
 * Saturday and Sunday come free) starting well before today, so the whole
 * default 14-day horizon is covered by one assignment.
 */
function upcomingShift(User $employee): Shift
{
    $shift = Shift::factory()->create(['organization_id' => $employee->organization_id]);

    ShiftAssignment::factory()->create([
        'organization_id' => $employee->organization_id,
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
        'start_date' => upcomingToday()->copy()->subMonth(),
        'end_date' => null,
    ]);

    return $shift;
}

/** The next date after today that the default Mon–Fri shift actually works. */
function nextWorkingDate(): Carbon
{
    $date = upcomingToday()->copy()->addDay();

    while ($date->isWeekend()) {
        $date->addDay();
    }

    return $date;
}

/**
 * The last date within an N-day horizon the default Mon–Fri shift actually
 * works: the resolver drops free (weekend) days entirely (see
 * ShiftScheduleResolver::resolveDate), so when the horizon's own end date
 * lands on a weekend the days list's last entry is the working day before it,
 * not the weekend date itself.
 */
function lastWorkingDateWithinHorizon(int $days): Carbon
{
    $date = upcomingToday()->copy()->addDays($days);

    while ($date->isWeekend()) {
        $date->subDay();
    }

    return $date;
}

// --- Authentication and authorization ---

test('an unauthenticated request for upcoming shifts returns 401', function () {
    $this->getJson('/api/v1/me/shifts/upcoming')->assertUnauthorized();
});

test('an employee without ViewOwn:Workday is forbidden', function () {
    $employee = User::factory()->create(); // no role, no permissions
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/shifts/upcoming')->assertForbidden();
});

// --- The days horizon (#2 of KOL-64) ---

test('the days param controls the horizon and defaults to 14', function () {
    $employee = upcomingEmployee();
    upcomingShift($employee);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/shifts/upcoming')->assertOk();
    $lastDate = Carbon::parse(collect($response->json('days'))->last()['date']);

    expect($lastDate->toDateString())->toBe(lastWorkingDateWithinHorizon(14)->toDateString());
});

test('the days param is capped at 30', function () {
    $employee = upcomingEmployee();
    upcomingShift($employee);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/shifts/upcoming?days=90')->assertOk();
    $lastDate = collect($response->json('days'))->last()['date'];
    // 'Y-m-d' strings, compared as strings throughout — the lesson the leave
    // and holiday annotation bugs already taught this file: a Carbon instant
    // built from `upcomingToday()`'s Santiago timezone is not the same moment
    // as one built from a bare date string, and a calendar-date boundary check
    // has no business going through an instant comparison at all.
    $capDate = upcomingToday()->copy()->addDays(30)->toDateString();
    $minDate = upcomingToday()->copy()->addDays(28)->toDateString();

    // The cap's own date can itself be a skipped weekend, so the last entry is
    // whichever working day falls on or before it — never later, and never as
    // far out as the uncapped days=90 would have reached.
    expect($lastDate <= $capDate)->toBeTrue()
        ->and($lastDate >= $minDate)->toBeTrue();
});

// --- Today (#3, #4 of KOL-64) ---

test('today carries the shift scheduled for the current date', function () {
    $employee = upcomingEmployee();
    upcomingShift($employee);
    Sanctum::actingAs($employee);

    // The fixture is Mon–Fri; skip the assertion's own shape on a weekend run
    // rather than fight the calendar for a fixed test date.
    if (upcomingToday()->isWeekend()) {
        $this->markTestSkipped('today is a free day in the Mon–Fri fixture');
    }

    $this->getJson('/api/v1/me/shifts/upcoming')
        ->assertOk()
        ->assertJsonPath('today.premise', 'Sucursal Ñuñoa')
        ->assertJsonPath('today.start_time', '08:00:00')
        ->assertJsonPath('today.end_time', '17:00:00');
});

test('today is null when nothing is scheduled', function () {
    $employee = upcomingEmployee();
    Sanctum::actingAs($employee); // no shift assignment at all

    $this->getJson('/api/v1/me/shifts/upcoming')
        ->assertOk()
        ->assertJsonPath('today', null);
});

test('the top-level date names the employee\'s own day even when today is null', function () {
    $employee = upcomingEmployee();
    Sanctum::actingAs($employee); // no shift assignment at all — today stays null

    $this->getJson('/api/v1/me/shifts/upcoming')
        ->assertOk()
        ->assertJsonPath('date', upcomingToday()->toDateString())
        ->assertJsonPath('today', null);
});

// --- punch_state gating (#4 of KOL-64) ---

test('punch_state is present when the employee holds ClockOwn:Mark', function () {
    $employee = upcomingEmployee();
    upcomingShift($employee);
    Sanctum::actingAs($employee);

    if (upcomingToday()->isWeekend()) {
        $this->markTestSkipped('today is a free day in the Mon–Fri fixture');
    }

    $this->getJson('/api/v1/me/shifts/upcoming')
        ->assertOk()
        ->assertJsonPath('today.punch_state', 'before');
});

test('punch_state is absent, not null, without ClockOwn:Mark', function () {
    $employee = upcomingEmployee();
    upcomingShift($employee);
    // Off the employee role entirely, onto a bare ViewOwn:Workday grant — the
    // route's own gate needs to pass, and ClockOwn:Mark needs to genuinely be
    // absent, which stripping the whole role and re-granting one permission
    // proves more honestly than trying to subtract from the role in place.
    $employee->syncRoles([]);
    $employee->givePermissionTo('ViewOwn:Workday');
    Sanctum::actingAs($employee->fresh());

    if (upcomingToday()->isWeekend()) {
        $this->markTestSkipped('today is a free day in the Mon–Fri fixture');
    }

    $response = $this->getJson('/api/v1/me/shifts/upcoming')->assertOk();

    expect($response->json('today'))->not->toHaveKey('punch_state');
});

// --- The days list (#5 of KOL-64) ---

test('the days list is in order and skips free (weekend) days', function () {
    $employee = upcomingEmployee();
    upcomingShift($employee);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/shifts/upcoming')->assertOk();
    $dates = collect($response->json('days'))->pluck('date');

    // Every returned date is a weekday, and none repeats or goes backwards.
    foreach ($dates as $date) {
        expect(Carbon::parse($date)->isWeekend())->toBeFalse();
    }
    expect($dates->values()->all())->toBe($dates->sort()->values()->all());
});

test('a normal upcoming day reports the full schedule', function () {
    $employee = upcomingEmployee();
    upcomingShift($employee);
    Sanctum::actingAs($employee);

    $date = nextWorkingDate();

    $this->getJson('/api/v1/me/shifts/upcoming')
        ->assertOk()
        ->assertJsonFragment([
            'date' => $date->toDateString(),
            'premise' => 'Sucursal Ñuñoa',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'lunch_start_time' => '12:00:00',
            'lunch_end_time' => '13:00:00',
            'leave_type_label' => null,
            'holiday_name' => null,
        ]);
});

// --- Leave annotation (#6 of KOL-64) ---

test('a date covered by an approved leave reports the leave type and no schedule', function () {
    $employee = upcomingEmployee();
    upcomingShift($employee);
    Sanctum::actingAs($employee);

    $date = nextWorkingDate();

    Leave::factory()->approved()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => $date->toDateString(),
        'end_date' => $date->toDateString(),
        'created_by' => $employee->id,
    ]);

    $this->getJson('/api/v1/me/shifts/upcoming')
        ->assertOk()
        ->assertJsonFragment([
            'date' => $date->toDateString(),
            'leave_type_label' => LeaveType::Vacation->label(),
            'start_time' => null,
            'end_time' => null,
        ]);
});

test('a pending (not yet approved) leave does not annotate the date', function () {
    $employee = upcomingEmployee();
    upcomingShift($employee);
    Sanctum::actingAs($employee);

    $date = nextWorkingDate();

    Leave::factory()->pending()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'start_date' => $date->toDateString(),
        'end_date' => $date->toDateString(),
        'created_by' => $employee->id,
    ]);

    $this->getJson('/api/v1/me/shifts/upcoming')
        ->assertOk()
        ->assertJsonFragment([
            'date' => $date->toDateString(),
            'leave_type_label' => null,
            'start_time' => '08:00:00',
        ]);
});

// --- Holiday annotation (#7 of KOL-64) ---

test('a holiday date reports the holiday name and no schedule', function () {
    $employee = upcomingEmployee();
    $shift = upcomingShift($employee);
    $shift->update(['work_on_holidays' => false]);
    Sanctum::actingAs($employee);

    $date = nextWorkingDate();

    Holiday::factory()->create([
        'organization_id' => null,
        'name' => 'Fiestas Patrias',
        'date' => $date->toDateString(),
    ]);

    $this->getJson('/api/v1/me/shifts/upcoming')
        ->assertOk()
        ->assertJsonFragment([
            'date' => $date->toDateString(),
            'holiday_name' => 'Fiestas Patrias',
            'start_time' => null,
            'end_time' => null,
        ]);
});

test('a holiday does not annotate the date when the shift works holidays', function () {
    $employee = upcomingEmployee();
    $shift = upcomingShift($employee);
    $shift->update(['work_on_holidays' => true]);
    Sanctum::actingAs($employee);

    $date = nextWorkingDate();

    Holiday::factory()->create([
        'organization_id' => null,
        'name' => 'Fiestas Patrias',
        'date' => $date->toDateString(),
    ]);

    $this->getJson('/api/v1/me/shifts/upcoming')
        ->assertOk()
        ->assertJsonFragment([
            'date' => $date->toDateString(),
            'holiday_name' => null,
            'start_time' => '08:00:00',
        ]);
});
