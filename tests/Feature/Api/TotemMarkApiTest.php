<?php

use App\Models\Mark;
use App\Models\Organization;
use App\Models\Premise;
use App\Models\TotemDevice;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(Carbon::parse('2026-08-07 12:00:00', 'UTC'));
});

/**
 * @return array{0: TotemDevice, 1: User, 2: Premise}
 */
function totemWithEmployee(?float $radius = null): array
{
    $organization = Organization::factory()->create();
    $premise = Premise::factory()->create([
        'organization_id' => $organization->id,
        'lat' => -33.45,
        'lng' => -70.66,
        'geofence_radius_meters' => $radius,
    ]);
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'premise_id' => $premise->id,
        'rut' => '12345678-5',
        'password' => 'employee-secret',
        'timezone' => 'America/Santiago',
    ]);
    $device = TotemDevice::factory()->forPremise($premise)->create();

    return [$device, $employee, $premise];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function totemPunch(array $overrides = []): array
{
    return array_merge(['rut' => '12345678-5', 'password' => 'employee-secret', 'type' => 'IN'], $overrides);
}

test('a valid rut and password records a mark for that employee', function () {
    [$device, $employee] = totemWithEmployee();
    Sanctum::actingAs($device, ['kiosk:punch']);

    $this->postJson('/api/v1/totem/marks', totemPunch())
        ->assertCreated()
        ->assertJsonStructure(['mark_id', 'hash', 'datetime', 'type', 'geo_status']);

    expect(Mark::withoutGlobalScopes()->sole()->user_id)->toBe($employee->id);
});

test('a rut typed with dots and a lowercase k still identifies the employee', function () {
    [$device, $employee] = totemWithEmployee();
    $employee->forceFill(['rut' => '11111111-K'])->save();
    Sanctum::actingAs($device, ['kiosk:punch']);

    $this->postJson('/api/v1/totem/marks', totemPunch(['rut' => '11.111.111-k']))->assertCreated();
});

test('a wrong password is rejected and records nothing', function () {
    [$device] = totemWithEmployee();
    Sanctum::actingAs($device, ['kiosk:punch']);

    $this->postJson('/api/v1/totem/marks', totemPunch(['password' => 'nope']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rut');

    expect(Mark::withoutGlobalScopes()->count())->toBe(0);
});

test('an unknown rut is rejected with the same error as a wrong password', function () {
    [$device] = totemWithEmployee();
    Sanctum::actingAs($device, ['kiosk:punch']);

    $wrongPassword = $this->postJson('/api/v1/totem/marks', totemPunch(['password' => 'nope']))->json();
    $unknownRut = $this->postJson('/api/v1/totem/marks', totemPunch(['rut' => '99999999-9']))
        ->assertUnprocessable()
        ->json();

    expect($unknownRut)->toBe($wrongPassword);
});

test('an employee of another premise cannot punch at this totem', function () {
    [$device, $employee] = totemWithEmployee();
    $employee->update(['premise_id' => Premise::factory()->create(['organization_id' => $employee->organization_id])->id]);
    Sanctum::actingAs($device, ['kiosk:punch']);

    $this->postJson('/api/v1/totem/marks', totemPunch())->assertUnprocessable();

    expect(Mark::withoutGlobalScopes()->count())->toBe(0);
});

test('a second in on the same day answers 409', function () {
    [$device] = totemWithEmployee();
    Sanctum::actingAs($device, ['kiosk:punch']);

    $this->postJson('/api/v1/totem/marks', totemPunch())->assertCreated();
    $this->postJson('/api/v1/totem/marks', totemPunch())->assertConflict();
});

test('a revoked device token can no longer punch', function () {
    [$device] = totemWithEmployee();
    $token = $device->issuePunchToken();
    $device->revoke();
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->postJson('/api/v1/totem/marks', totemPunch())->assertUnauthorized();
});

test('an employee token cannot use the totem endpoint', function () {
    [, $employee] = totemWithEmployee();
    Sanctum::actingAs($employee, ['*']);

    $this->postJson('/api/v1/totem/marks', totemPunch())->assertForbidden();
});

test('a queued punch is stamped with its device time and retried idempotently', function () {
    [$device] = totemWithEmployee();
    Sanctum::actingAs($device, ['kiosk:punch']);
    $body = totemPunch([
        'device_datetime' => '2026-08-07 06:30:00',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->postJson('/api/v1/totem/marks', $body)
        ->assertCreated()
        ->assertJsonPath('datetime', '2026-08-07 06:30:00')
        ->assertJsonPath('captured_offline', true);

    $this->postJson('/api/v1/totem/marks', $body)->assertOk();

    expect(Mark::withoutGlobalScopes()->count())->toBe(1);
});

test('a queued punch from the future is refused with its code', function () {
    [$device] = totemWithEmployee();
    Sanctum::actingAs($device, ['kiosk:punch']);

    $this->postJson('/api/v1/totem/marks', totemPunch([
        'device_datetime' => '2026-08-07 20:00:00',
        'idempotency_key' => (string) Str::uuid(),
    ]))->assertUnprocessable()->assertJsonPath('code', 'queued_punch_in_future');
});

test('a queued punch beyond the offline cap is refused with its code', function () {
    [$device] = totemWithEmployee();
    Sanctum::actingAs($device, ['kiosk:punch']);

    $this->postJson('/api/v1/totem/marks', totemPunch([
        'device_datetime' => '2026-08-05 06:30:00',
        'idempotency_key' => (string) Str::uuid(),
    ]))->assertUnprocessable()->assertJsonPath('code', 'queued_punch_too_old');
});

test('geofence is evaluated at the premise coordinates when the totem sends none', function () {
    [$device] = totemWithEmployee(radius: 100);
    Sanctum::actingAs($device, ['kiosk:punch']);

    $response = $this->postJson('/api/v1/totem/marks', totemPunch())->assertCreated();

    expect($response->json('geo_status'))->not->toBe('unknown');
    $mark = Mark::withoutGlobalScopes()->sole();
    expect((float) $mark->lat)->toBe(-33.45)->and((float) $mark->lng)->toBe(-70.66);
});
