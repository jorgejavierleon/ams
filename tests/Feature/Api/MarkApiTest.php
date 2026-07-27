<?php

use App\Models\Mark;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

// --- Authentication ---

test('unauthenticated mark creation returns 401', function () {
    $this->postJson('/api/marks', [
        'type' => 'IN',
        'datetime' => '2026-07-24 09:00:00',
    ])->assertUnauthorized();

    expect(Mark::count())->toBe(0);
});

test('an authenticated employee creates a mark and receives its hash', function () {
    $employee = apiEmployee();
    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/marks', [
        'type' => 'IN',
        'datetime' => '2026-07-24 09:00:00',
        'lat' => -33.4489,
        'lng' => -70.6693,
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['mark_id', 'hash', 'datetime', 'type'])
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

    $this->postJson('/api/marks', [
        'type' => 'out',
        'datetime' => '2026-07-24 18:00:00',
    ])->assertCreated()->assertJsonPath('type', 'out');

    expect(Mark::first()->lat)->toBeNull()
        ->and(Mark::first()->lng)->toBeNull();
});

test('the punch type must be valid', function () {
    Sanctum::actingAs(apiEmployee());

    $this->postJson('/api/marks', [
        'type' => 'sideways',
        'datetime' => '2026-07-24 09:00:00',
    ])->assertStatus(422);
});

test('the datetime is required', function () {
    Sanctum::actingAs(apiEmployee());

    $this->postJson('/api/marks', ['type' => 'IN'])
        ->assertStatus(422);
});

test('the mark is created through MarkManager so the observer stamps the snapshot', function () {
    $employee = apiEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/marks', [
        'type' => 'IN',
        'datetime' => '2026-07-24 09:00:00',
    ])->assertCreated();

    // Checksum and the immutable legal snapshot are stamped by MarkObserver.
    expect(Mark::first())
        ->checksum->not->toBeEmpty()
        ->employee_name->toBe($employee->name);
});

test('a user without the clock permission cannot create a mark', function () {
    // A plain user with no employee role holds none of the self-service perms.
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/marks', [
        'type' => 'IN',
        'datetime' => '2026-07-24 09:00:00',
    ])->assertForbidden();

    expect(Mark::count())->toBe(0);
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

    $this->getJson('/api/marks')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonStructure([['mark_id', 'hash', 'datetime', 'type']]);
});

test('show is scoped to the authenticated employee', function () {
    $employee = apiEmployee();
    $mark = Mark::factory()->create([
        'user_id' => $employee->id,
        'organization_id' => $employee->organization_id,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson("/api/marks/{$mark->id}")
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

    $this->getJson("/api/marks/{$mark->id}")->assertNotFound();
});

// --- Token issuance ---

test('valid credentials issue a device token', function () {
    $employee = apiEmployee(); // factory password is "password"

    $this->postJson('/api/sanctum/token', [
        'email' => $employee->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertOk()->assertJsonStructure(['token']);

    expect($employee->fresh()->tokens()->count())->toBe(1);
});

test('re-authenticating from a device replaces its previous token', function () {
    $employee = apiEmployee();
    $employee->createToken('Pixel 8');

    $this->postJson('/api/sanctum/token', [
        'email' => $employee->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertOk();

    expect($employee->fresh()->tokens()->where('name', 'Pixel 8')->count())->toBe(1);
});

test('a deactivated employee cannot obtain a token', function () {
    $employee = apiEmployee();
    $employee->update(['is_active' => false]);

    $this->postJson('/api/sanctum/token', [
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

    $this->postJson('/api/sanctum/token', [
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
        ->postJson('/api/marks', [
            'type' => 'IN',
            'datetime' => '2026-07-24 09:00:00',
        ])
        ->assertCreated();

    expect(Mark::where('user_id', $employee->id)->count())->toBe(1);
});
