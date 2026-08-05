<?php

use App\Models\Mark;
use App\Models\Organization;
use App\Models\Premise;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    // Grants the self-service permissions (ClockOwn:Mark, ViewOwn:Mark) to the
    // `employee` role, which is what decides whether a punch state is reported.
    $this->seed(RoleSeeder::class);

    // MarkObserver mails a receipt for every punch an employee with a personal
    // address makes.
    Mail::fake();
});

/**
 * The employee's own wall-clock day. Every fixture here is built against it
 * rather than against `now()`: late in the UTC evening the server has already
 * rolled over to a day Santiago has not reached, and the endpoint answers for
 * the employee's day.
 */
function employeeToday(): Carbon
{
    return Carbon::now('America/Santiago');
}

/**
 * An employee attached to a premise, in their own organization. The premise
 * carries coordinates and a geofence radius unless overridden.
 *
 * @param  array<string, mixed>  $premiseAttributes
 */
function todayEmployee(?Organization $organization = null, array $premiseAttributes = []): User
{
    $organization ??= Organization::factory()->create();

    $premise = Premise::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Sucursal Ñuñoa',
        'lat' => -33.4569,
        'lng' => -70.5975,
        'geofence_radius_meters' => 150,
        ...$premiseAttributes,
    ]);

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'premise_id' => $premise->id,
        'timezone' => 'America/Santiago',
    ]);
}

/**
 * Assign the employee a shift whose day for today carries the given window.
 * Passing `is_free` makes today a non-working day; passing null lunch times
 * makes it a shift with no colación.
 *
 * @param  array<string, mixed>  $today
 */
function todayShift(User $employee, array $today = [], float $contractedHours = 44): Shift
{
    $shift = Shift::factory()->create(['organization_id' => $employee->organization_id]);

    // ShiftObserver seeds a row for all seven weekdays; only today's matters.
    $shift->days()
        ->where('weekday', (int) employeeToday()->format('N') - 1)
        ->first()
        ->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'lunch_start_time' => '13:00:00',
            'lunch_end_time' => '14:00:00',
            'is_free' => false,
            ...$today,
        ]);

    ShiftAssignment::factory()->create([
        'organization_id' => $employee->organization_id,
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
    ]);

    // Written last: ShiftDayObserver rolls the weekly total up from the days
    // whenever one of them changes, and the contracted total under test is the
    // shift's own (decision D-F1-d), not a sum of the demo schedule.
    $shift->total_week_hours = $contractedHours;
    $shift->save();

    return $shift;
}

// --- Authentication (#1) ---

test('an unauthenticated request for today returns 401', function () {
    $this->getJson('/api/v1/me/today')->assertUnauthorized();
});

test('the home screen answers with date, shift, punch and week', function () {
    $employee = todayEmployee();
    todayShift($employee);
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonStructure([
            'date',
            'shift' => ['premise', 'start_time', 'end_time', 'lunch_start_time', 'lunch_end_time'],
            'punch' => ['state'],
            'week' => ['worked_hours', 'contracted_hours'],
        ])
        ->assertJsonPath('date', employeeToday()->format('Y-m-d'))
        ->assertJsonPath('shift.premise', 'Sucursal Ñuñoa')
        ->assertJsonPath('shift.start_time', '08:00:00')
        ->assertJsonPath('shift.end_time', '17:00:00')
        ->assertJsonPath('shift.lunch_start_time', '13:00:00')
        ->assertJsonPath('shift.lunch_end_time', '14:00:00')
        ->assertJsonPath('week.contracted_hours', 44);
});

// --- Naive wall-clock values (#2) ---

test('every date and time is a naive wall-clock string with no offset', function () {
    $employee = todayEmployee();
    todayShift($employee);
    Sanctum::actingAs($employee);

    $payload = $this->getJson('/api/v1/me/today')->assertOk()->json();

    expect($payload['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');

    foreach (['start_time', 'end_time', 'lunch_start_time', 'lunch_end_time'] as $field) {
        expect($payload['shift'][$field])->toMatch('/^\d{2}:\d{2}:\d{2}$/');
    }
});

// --- Nothing scheduled (#3) ---

test('shift is null on a free day', function () {
    $employee = todayEmployee();
    todayShift($employee, ['is_free' => true]);
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('shift', null);
});

test('shift is null for an employee with no active assignment', function () {
    $employee = todayEmployee();
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('shift', null)
        ->assertJsonPath('week.contracted_hours', 0);
});

test('an assignment that already ended leaves the day unscheduled', function () {
    $employee = todayEmployee();
    $shift = todayShift($employee);
    ShiftAssignment::query()->where('user_id', $employee->id)->delete();
    ShiftAssignment::factory()->ended()->create([
        'organization_id' => $employee->organization_id,
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('shift', null);
});

// --- Colación travels both ends or neither (#4) ---

test('a shift with no colación window sends neither end of it', function () {
    $employee = todayEmployee();
    todayShift($employee, ['lunch_start_time' => null, 'lunch_end_time' => null]);
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('shift.start_time', '08:00:00')
        ->assertJsonPath('shift.lunch_start_time', null)
        ->assertJsonPath('shift.lunch_end_time', null);
});

test('half a colación window is reported as none at all', function () {
    $employee = todayEmployee();
    todayShift($employee, ['lunch_end_time' => null]);
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('shift.lunch_start_time', null)
        ->assertJsonPath('shift.lunch_end_time', null);
});

// --- The premise geofence (KOL-33) ---

test('the shift carries the premise coordinates and radius', function () {
    $employee = todayEmployee();
    todayShift($employee);
    Sanctum::actingAs($employee);

    $payload = $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('shift.geofence.lat', -33.4569)
        ->assertJsonPath('shift.geofence.lng', -70.5975)
        ->assertJsonPath('shift.geofence.radius_meters', 150)
        ->json();

    // The client's parser rejects quoted coordinates outright, which is what a
    // `decimal:8` cast on the model would emit.
    expect($payload['shift']['geofence']['lat'])->toBeFloat();
    expect($payload['shift']['geofence']['lng'])->toBeFloat();
    expect($payload['shift']['geofence']['radius_meters'])->toBeInt();
});

test('a premise with coordinates but no radius sends a null radius, not an error', function () {
    $employee = todayEmployee(premiseAttributes: ['geofence_radius_meters' => null]);
    todayShift($employee);
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('shift.geofence.lat', -33.4569)
        ->assertJsonPath('shift.geofence.radius_meters', null);
});

test('a premise with no coordinates has no geofence at all', function () {
    $employee = todayEmployee(premiseAttributes: ['lat' => null, 'lng' => null]);
    todayShift($employee);
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('shift.premise', 'Sucursal Ñuñoa')
        ->assertJsonPath('shift.geofence', null);
});

test('an employee attached to no premise has no geofence', function () {
    $employee = todayEmployee();
    $employee->update(['premise_id' => null]);
    todayShift($employee);
    Sanctum::actingAs($employee->fresh());

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('shift.geofence', null);
});

test('the geofence block costs no query of its own', function () {
    $organization = Organization::factory()->create();

    $fenced = todayEmployee($organization);
    todayShift($fenced);

    $unfenced = todayEmployee($organization, ['lat' => null, 'lng' => null]);
    todayShift($unfenced);

    $countQueries = function (User $employee): int {
        $queries = 0;
        $listener = function () use (&$queries): void {
            $queries++;
        };

        Sanctum::actingAs($employee);
        DB::listen($listener);
        $this->getJson('/api/v1/me/today')->assertOk();

        return $queries;
    };

    // The premise is already read for the shift card's label; the geofence is
    // the same row seen twice.
    expect($countQueries($fenced))->toBe($countQueries($unfenced));
});

// --- Punch state (#5) ---

test('punch state is derived from today\'s marks', function (array $types, string $expected) {
    $employee = todayEmployee();
    todayShift($employee);

    foreach ($types as $type) {
        Mark::factory()->create([
            'organization_id' => $employee->organization_id,
            'user_id' => $employee->id,
            'type' => $type,
            'date_time' => employeeToday()->setTime(9, 0),
        ]);
    }

    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('punch.state', $expected);
})->with([
    'no punches yet' => [[], 'before'],
    'entrada only' => [['in'], 'working'],
    'entrada and salida' => [['in', 'out'], 'done'],
]);

test('yesterday\'s punches do not carry into today', function () {
    $employee = todayEmployee();
    todayShift($employee);
    Mark::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => 'in',
        'date_time' => employeeToday()->subDay()->setTime(9, 0),
    ]);
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('punch.state', 'before');
});

// --- The week (#6) ---

test('worked hours sum the week to date and ignore the week before', function () {
    $employee = todayEmployee();
    todayShift($employee, contractedHours: 44);

    $weekStart = employeeToday()->startOfWeek(Carbon::MONDAY);

    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => $weekStart->toDateString(),
        'worked_time' => '08:30:00',
    ]);
    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => $weekStart->copy()->subDay()->toDateString(),
        'worked_time' => '07:00:00',
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('week.worked_hours', 8.5)
        ->assertJsonPath('week.contracted_hours', 44);
});

test('another employee\'s workdays are not counted', function () {
    $organization = Organization::factory()->create();
    $employee = todayEmployee($organization);
    $colleague = todayEmployee($organization);
    todayShift($employee);

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $colleague->id,
        'date' => employeeToday()->startOfWeek(Carbon::MONDAY)->toDateString(),
        'worked_time' => '08:00:00',
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('week.worked_hours', 0);
});

test('hours are never negative', function () {
    $employee = todayEmployee();
    todayShift($employee);

    Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => employeeToday()->startOfWeek(Carbon::MONDAY)->toDateString(),
        'worked_time' => '-01:00:00',
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('week.worked_hours', 0);
});

// --- The employee who does not punch (#7) ---

test('a user without ClockOwn:Mark still gets the shift and the week', function () {
    $employee = todayEmployee();
    $employee->syncRoles([]);
    $employee->syncPermissions([]);
    todayShift($employee);

    Sanctum::actingAs($employee->fresh());

    $this->getJson('/api/v1/me/today')
        ->assertOk()
        ->assertJsonPath('punch', null)
        ->assertJsonPath('shift.premise', 'Sucursal Ñuñoa')
        ->assertJsonPath('shift.start_time', '08:00:00')
        ->assertJsonPath('week.contracted_hours', 44);
});

// --- One request, a fixed number of queries (#8) ---

test('the response costs the same queries however long the week is', function () {
    $organization = Organization::factory()->create();

    // Two employees measured cold, one apiece: the same request against a week
    // with nothing in it and against a full one. Reusing a single employee would
    // measure Eloquent's in-memory relation cache instead.
    $quiet = todayEmployee($organization);
    todayShift($quiet);

    $busy = todayEmployee($organization);
    todayShift($busy);

    $weekStart = employeeToday()->startOfWeek(Carbon::MONDAY);
    foreach (range(0, 4) as $offset) {
        Workday::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $busy->id,
            'date' => $weekStart->copy()->addDays($offset)->toDateString(),
            'worked_time' => '08:00:00',
        ]);
    }
    foreach (['in', 'out'] as $type) {
        Mark::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $busy->id,
            'type' => $type,
            'date_time' => employeeToday()->setTime(9, 0),
        ]);
    }

    $countQueries = function (User $employee): int {
        $queries = 0;
        $listener = function () use (&$queries): void {
            $queries++;
        };

        Sanctum::actingAs($employee);
        DB::listen($listener);
        $this->getJson('/api/v1/me/today')->assertOk();

        return $queries;
    };

    // A week of workdays and a punch pair cost nothing: every read is a fixed
    // query, none of them per-row.
    expect($countQueries($busy))->toBe($countQueries($quiet));
});
