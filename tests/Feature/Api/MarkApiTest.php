<?php

use App\Enums\GeoStatus;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\Premise;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    // Grants the self-service permissions (ClockOwn:Mark, ViewOwn:Mark) to the
    // `employee` role that the API routes gate on.
    $this->seed(RoleSeeder::class);
});

function apiEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
    ]);
}

/**
 * An employee attached to a premise at the given point, with the given geofence
 * radius (null = no geofence configured).
 */
function apiEmployeeAtPremise(?float $lat = -33.4489, ?float $lng = -70.6693, ?int $radiusMeters = 150): User
{
    $employee = apiEmployee();

    $premise = Premise::factory()->create([
        'organization_id' => $employee->organization_id,
        'lat' => $lat,
        'lng' => $lng,
        'geofence_radius_meters' => $radiusMeters,
    ]);

    $employee->update(['premise_id' => $premise->id]);

    return $employee->fresh();
}

/**
 * The punch body the mobile client sends: every location key present, an
 * explicit null where there was nothing to report, and never a `datetime`.
 *
 * @return array<string, mixed>
 */
function punchBody(string $type, ?float $lat = null, ?float $lng = null, ?float $accuracy = null, string $geoStatus = 'unknown'): array
{
    return [
        'type' => $type,
        'lat' => $lat,
        'lng' => $lng,
        'accuracy_m' => $accuracy,
        'geo_status' => $geoStatus,
    ];
}

// --- Authentication ---

test('unauthenticated mark creation returns 401', function () {
    $this->postJson('/api/v1/marks', punchBody('IN'))->assertUnauthorized();

    expect(Mark::count())->toBe(0);
});

test('an authenticated employee creates a mark and receives its hash', function () {
    $employee = apiEmployee();
    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/v1/marks', punchBody('IN', -33.4489, -70.6693, 12.4, 'inside'));

    $response->assertCreated()
        ->assertJsonStructure(['mark_id', 'hash', 'datetime', 'type', 'geo_status'])
        ->assertJsonPath('type', 'in');

    $mark = Mark::first();

    expect($mark)->not->toBeNull()
        ->and($mark->user_id)->toBe($employee->id)
        ->and($mark->organization_id)->toBe($employee->organization_id)
        ->and($mark->checksum)->not->toBeEmpty()
        ->and((float) $mark->lat)->toBe(-33.4489)
        ->and((float) $mark->lng)->toBe(-70.6693)
        ->and($response->json('mark_id'))->toBe($mark->id)
        ->and($response->json('hash'))->toBe($mark->checksum);
});

test('geolocation is optional', function () {
    Sanctum::actingAs(apiEmployee());

    $this->postJson('/api/v1/marks', ['type' => 'out'])
        ->assertCreated()
        ->assertJsonPath('type', 'out');

    expect(Mark::first()->lat)->toBeNull()
        ->and(Mark::first()->lng)->toBeNull();
});

test('the punch type must be valid', function () {
    Sanctum::actingAs(apiEmployee());

    $this->postJson('/api/v1/marks', punchBody('sideways'))->assertStatus(422);
});

test('the mark is created through MarkManager so the observer stamps the snapshot', function () {
    $employee = apiEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/marks', punchBody('IN'))->assertCreated();

    // Checksum and the immutable legal snapshot are stamped by MarkObserver.
    expect(Mark::first())
        ->checksum->not->toBeEmpty()
        ->employee_name->toBe($employee->name);
});

test('a user without the clock permission cannot create a mark', function () {
    // A plain user with no employee role holds none of the self-service perms.
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/marks', punchBody('IN'))->assertForbidden();

    expect(Mark::count())->toBe(0);
});

// --- The server owns the timestamp ---

test('a client-supplied datetime is rejected rather than ignored', function () {
    Sanctum::actingAs(apiEmployee());

    $this->postJson('/api/v1/marks', [...punchBody('IN'), 'datetime' => '2026-07-24 09:00:00'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['datetime']);

    expect(Mark::count())->toBe(0);
});

test('the punch is stamped server side in the employee timezone', function () {
    $employee = apiEmployee();
    $employee->update(['timezone' => 'America/Santiago']);
    Sanctum::actingAs($employee);

    $this->travelTo(Carbon::parse('2026-07-24 12:00:00', 'UTC'), function () {
        $this->postJson('/api/v1/marks', punchBody('IN'))->assertCreated();
    });

    // 12:00 UTC is 08:00 in Santiago, and the wall-clock reading is what is
    // stored: the register is read in the employee's own day, not the server's.
    expect(Mark::first()->date_time->format('Y-m-d H:i'))->toBe('2026-07-24 08:00');
});

// --- The server owns the geofence verdict ---

test('a punch inside the radius is recorded as inside', function () {
    Sanctum::actingAs(apiEmployeeAtPremise());

    // ~50 m north of the premise, well inside its 150 m radius.
    $this->postJson('/api/v1/marks', punchBody('IN', -33.44845, -70.6693, 8.0, 'inside'))
        ->assertCreated()
        ->assertJsonPath('geo_status', 'inside');

    expect(Mark::first()->geo_status)->toBe(GeoStatus::Inside);
});

test('a punch outside the radius is recorded and flagged, never blocked', function () {
    Sanctum::actingAs(apiEmployeeAtPremise());

    // ~1.1 km north of the premise.
    $this->postJson('/api/v1/marks', punchBody('IN', -33.4389, -70.6693, 10.0, 'outside'))
        ->assertCreated()
        ->assertJsonPath('geo_status', 'outside');

    expect(Mark::first()->geo_status)->toBe(GeoStatus::Outside);
});

test('the client reported geo status never decides the stored verdict', function () {
    Sanctum::actingAs(apiEmployeeAtPremise());

    // The device insists it is inside; the coordinates it sent say otherwise,
    // and the coordinates are what the register is built from.
    $this->postJson('/api/v1/marks', punchBody('IN', -33.4389, -70.6693, 10.0, 'inside'))
        ->assertCreated()
        ->assertJsonPath('geo_status', 'outside');
});

test('a punch with no fix is recorded as unknown', function () {
    Sanctum::actingAs(apiEmployeeAtPremise());

    // An employee who denied location permission still punches: attendance that
    // cannot be recorded is a legal problem, not a product one.
    $this->postJson('/api/v1/marks', punchBody('IN', null, null, null, 'unknown'))
        ->assertCreated()
        ->assertJsonPath('geo_status', 'unknown');

    expect(Mark::first())
        ->geo_status->toBe(GeoStatus::Unknown)
        ->lat->toBeNull()
        ->lng->toBeNull();
});

test('a premise with no configured radius answers unknown', function () {
    Sanctum::actingAs(apiEmployeeAtPremise(radiusMeters: null));

    $this->postJson('/api/v1/marks', punchBody('IN', -33.4489, -70.6693, 8.0, 'unknown'))
        ->assertCreated()
        ->assertJsonPath('geo_status', 'unknown');
});

test('a premise with no coordinates answers unknown', function () {
    Sanctum::actingAs(apiEmployeeAtPremise(lat: null, lng: null, radiusMeters: 150));

    $this->postJson('/api/v1/marks', punchBody('IN', -33.4489, -70.6693, 8.0, 'unknown'))
        ->assertCreated()
        ->assertJsonPath('geo_status', 'unknown');
});

test('an employee attached to no premise answers unknown', function () {
    Sanctum::actingAs(apiEmployee());

    $this->postJson('/api/v1/marks', punchBody('IN', -33.4489, -70.6693, 8.0, 'inside'))
        ->assertCreated()
        ->assertJsonPath('geo_status', 'unknown');
});

test('the reported accuracy is persisted in metres', function () {
    Sanctum::actingAs(apiEmployeeAtPremise());

    $this->postJson('/api/v1/marks', punchBody('IN', -33.4489, -70.6693, 12.4, 'inside'))
        ->assertCreated();

    expect((float) Mark::first()->accuracy_meters)->toBe(12.4);
});

test('geolocation stays outside the integrity checksum', function () {
    $employee = apiEmployeeAtPremise();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/marks', punchBody('IN', -33.4389, -70.6693, 12.4, 'inside'))
        ->assertCreated();

    $mark = Mark::first();

    // The checksum covers who punched, which way and when — and nothing else,
    // so attaching the verdict afterwards cannot invalidate it.
    expect($mark->checksum)->toBe(
        hash('sha256', $employee->id.'in'.$mark->date_time->toIso8601String()),
    );
});

// --- One in and one out per day ---

test('a second in on the same day is refused with 409', function () {
    Sanctum::actingAs(apiEmployeeAtPremise());

    $this->postJson('/api/v1/marks', punchBody('IN'))->assertCreated();

    $this->postJson('/api/v1/marks', punchBody('IN'))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Ya registraste tu entrada de hoy.');

    expect(Mark::count())->toBe(1);
});

test('a second out on the same day is refused with 409', function () {
    Sanctum::actingAs(apiEmployeeAtPremise());

    $this->postJson('/api/v1/marks', punchBody('OUT'))->assertCreated();

    $this->postJson('/api/v1/marks', punchBody('OUT'))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Ya registraste tu salida de hoy.');

    expect(Mark::count())->toBe(1);
});

test('an out after an in on the same day is allowed', function () {
    Sanctum::actingAs(apiEmployeeAtPremise());

    $this->postJson('/api/v1/marks', punchBody('IN'))->assertCreated();
    $this->postJson('/api/v1/marks', punchBody('OUT'))->assertCreated();

    expect(Mark::count())->toBe(2);
});

test('yesterday punch does not block today', function () {
    $employee = apiEmployeeAtPremise();
    Sanctum::actingAs($employee);

    Mark::factory()->create([
        'user_id' => $employee->id,
        'organization_id' => $employee->organization_id,
        'type' => 'in',
        'date_time' => now()->subDay(),
    ]);

    $this->postJson('/api/v1/marks', punchBody('IN'))->assertCreated();
});

// --- The datetime on the wire ---

test('the receipt datetime is a naive wall clock string', function () {
    Sanctum::actingAs(apiEmployee());

    $response = $this->postJson('/api/v1/marks', punchBody('IN'))->assertCreated();

    // `2026-08-05 08:03:11`, never `2026-08-05T08:03:11-04:00`: an offset would
    // be re-read in the device's timezone and move a legal timestamp.
    expect($response->json('datetime'))->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

// --- Reads ---

test('index returns the employee own recent marks', function () {
    $employee = apiEmployee();
    $other = apiEmployee($employee->organization);

    Mark::factory()->count(2)->create([
        'user_id' => $employee->id,
        'organization_id' => $employee->organization_id,
    ]);
    Mark::factory()->create([
        'user_id' => $other->id,
        'organization_id' => $employee->organization_id,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/marks')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonStructure([['mark_id', 'hash', 'datetime', 'type', 'geo_status']]);
});

test('show is scoped to the authenticated employee', function () {
    $employee = apiEmployee();
    $mark = Mark::factory()->create([
        'user_id' => $employee->id,
        'organization_id' => $employee->organization_id,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson("/api/v1/marks/{$mark->id}")
        ->assertOk()
        ->assertJsonPath('mark_id', $mark->id);
});

test('an employee cannot view another employee mark', function () {
    $employee = apiEmployee();
    $other = apiEmployee($employee->organization);
    $mark = Mark::factory()->create([
        'user_id' => $other->id,
        'organization_id' => $employee->organization_id,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson("/api/v1/marks/{$mark->id}")->assertNotFound();
});

// --- Token issuance ---

test('valid credentials issue a device token', function () {
    $employee = apiEmployee(); // factory password is "password"

    $this->postJson('/api/v1/tokens', [
        'email' => $employee->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertOk()->assertJsonStructure(['token']);

    expect($employee->fresh()->tokens()->count())->toBe(1);
});

test('re-authenticating from a device replaces its previous token', function () {
    $employee = apiEmployee();
    $employee->createToken('Pixel 8');

    $this->postJson('/api/v1/tokens', [
        'email' => $employee->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertOk();

    expect($employee->fresh()->tokens()->where('name', 'Pixel 8')->count())->toBe(1);
});

test('a deactivated employee cannot obtain a token', function () {
    $employee = apiEmployee();
    $employee->update(['is_active' => false]);

    $this->postJson('/api/v1/tokens', [
        'email' => $employee->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    expect($employee->fresh()->tokens()->count())->toBe(0);
});

test('invalid credentials are rejected', function () {
    $employee = apiEmployee();

    $this->postJson('/api/v1/tokens', [
        'email' => $employee->email,
        'password' => 'wrong-password',
        'device_name' => 'Pixel 8',
    ])->assertStatus(422);

    expect($employee->fresh()->tokens()->count())->toBe(0);
});

test('a real device token authenticates subsequent requests end to end', function () {
    $employee = apiEmployee();
    $token = $employee->createToken('Pixel 8')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/marks', punchBody('IN'))
        ->assertCreated();

    expect(Mark::where('user_id', $employee->id)->count())->toBe(1);
});
