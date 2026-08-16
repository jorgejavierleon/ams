<?php

use App\Enums\OvertimeAuthorizationMode;
use App\Enums\OvertimeRequestStatus;
use App\Models\Organization;
use App\Models\OvertimeRequest;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seeds the roles and grants the self-service permissions to `employee`.
    $this->seed(RoleSeeder::class);
});

/**
 * A self-service employee whose tenant runs the given overtime authorisation
 * mode (Mode A only exists under pre-authorisation or combined).
 */
function otEmployee(OvertimeAuthorizationMode $mode = OvertimeAuthorizationMode::PreAuthorization, array $settingAttributes = []): User
{
    $organization = Organization::factory()->create();

    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => $mode,
        ...$settingAttributes,
    ]);

    return User::factory()->employee()->create(['organization_id' => $organization->id]);
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('my.overtime-requests.index'))->assertRedirect(route('login'));
});

test('a user without the self-service permissions is forbidden', function () {
    $user = User::factory()->create(); // no roles, so no permissions

    $this->actingAs($user)
        ->get(route('my.overtime-requests.index'))
        ->assertForbidden();
});

test('viewing does not grant requesting', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $user->givePermissionTo('ViewOwn:OvertimeAuthorization');

    $this->actingAs($user)
        ->get(route('my.overtime-requests.create'))
        ->assertForbidden();
});

// --- Hidden entirely under pure post-hoc mode (AC #6) ---

test('the request flow is hidden entirely under pure post-hoc mode', function () {
    $employee = otEmployee(OvertimeAuthorizationMode::PostHoc);

    $this->actingAs($employee)->get(route('my.overtime-requests.index'))->assertNotFound();
    $this->actingAs($employee)->get(route('my.overtime-requests.create'))->assertNotFound();
    $this->actingAs($employee)->post(route('my.overtime-requests.store'), [
        'date' => now()->toDateString(),
        'requested_hours' => '02:00',
    ])->assertNotFound();

    expect(OvertimeRequest::count())->toBe(0);
});

// --- Create (AC #1, #2, #7) ---

test('an employee can request overtime for the same day, with an optional reason', function () {
    $employee = otEmployee();

    $this->actingAs($employee)
        ->post(route('my.overtime-requests.store'), [
            'date' => now()->toDateString(),
            'requested_hours' => '02:00',
            'reason' => 'Cierre de inventario.',
        ])
        ->assertRedirect(route('my.overtime-requests.index'));

    $request = OvertimeRequest::first();

    expect($request)->not->toBeNull()
        ->and($request->user_id)->toBe($employee->id)
        ->and($request->organization_id)->toBe($employee->organization_id)
        ->and($request->status)->toBe(OvertimeRequestStatus::Pending)
        ->and($request->requested_hours)->toBe('02:00:00')
        ->and($request->reason)->toBe('Cierre de inventario.');
});

test('an employee can request overtime for a future date', function () {
    $employee = otEmployee();

    $this->actingAs($employee)
        ->post(route('my.overtime-requests.store'), [
            'date' => now()->addMonth()->toDateString(),
            'requested_hours' => '01:30',
        ])
        ->assertRedirect(route('my.overtime-requests.index'));

    expect(OvertimeRequest::count())->toBe(1);
});

test('a retroactive request inside the tenant window is accepted', function () {
    $employee = otEmployee(OvertimeAuthorizationMode::Combined, [
        'overtime_retroactive_request_days' => 5,
    ]);

    $this->actingAs($employee)
        ->post(route('my.overtime-requests.store'), [
            'date' => now()->subDays(3)->toDateString(),
            'requested_hours' => '02:00',
        ])
        ->assertRedirect(route('my.overtime-requests.index'));

    expect(OvertimeRequest::count())->toBe(1);
});

test('a retroactive request outside the tenant window is refused with a Spanish message naming the window', function () {
    $employee = otEmployee(OvertimeAuthorizationMode::Combined, [
        'overtime_retroactive_request_days' => 5,
    ]);

    $response = $this->actingAs($employee)
        ->post(route('my.overtime-requests.store'), [
            'date' => now()->subDays(10)->toDateString(),
            'requested_hours' => '02:00',
        ]);

    $response->assertSessionHasErrors([
        'date' => 'Solo puedes solicitar horas extra retroactivas dentro de los últimos 5 días.',
    ]);

    expect(OvertimeRequest::count())->toBe(0);
});

test('a request for zero hours is refused', function () {
    $employee = otEmployee();

    $response = $this->actingAs($employee)
        ->post(route('my.overtime-requests.store'), [
            'date' => now()->toDateString(),
            'requested_hours' => '00:00',
        ]);

    $response->assertSessionHasErrors([
        'requested_hours' => 'Las horas solicitadas deben ser mayores a 0.',
    ]);

    expect(OvertimeRequest::count())->toBe(0);
});

test('the retroactive window is judged by the Chilean calendar day, not the server UTC day', function () {
    // 02:00 UTC is still the previous evening in America/Santiago, so a
    // naive `Carbon::today()` (server runs in UTC) would compute a "today"
    // one day ahead of the employee's actual day, making the window one day
    // stricter than the tenant configured.
    Carbon::setTestNow('2026-08-16 02:00:00');

    $employee = otEmployee(OvertimeAuthorizationMode::Combined, [
        'overtime_retroactive_request_days' => 2,
    ]);

    // Correct (Santiago) "today" is 2026-08-15, so the earliest allowed date
    // is exactly 2026-08-13 — accepted only if "today" is resolved correctly.
    $this->actingAs($employee)
        ->post(route('my.overtime-requests.store'), [
            'date' => '2026-08-13',
            'requested_hours' => '02:00',
        ])
        ->assertRedirect(route('my.overtime-requests.index'));

    expect(OvertimeRequest::count())->toBe(1);

    Carbon::setTestNow();
});

test('the requester is always the authenticated employee', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $victim = User::factory()->employee()->create(['organization_id' => $organization->id]);

    $this->actingAs($employee)
        ->post(route('my.overtime-requests.store'), [
            // A forged user_id must be ignored.
            'user_id' => $victim->id,
            'date' => now()->toDateString(),
            'requested_hours' => '02:00',
        ])
        ->assertRedirect(route('my.overtime-requests.index'));

    expect(OvertimeRequest::first()->user_id)->toBe($employee->id);
});

// --- Index (AC #4) ---

test('an employee sees only their own request history and status', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $other = User::factory()->employee()->create(['organization_id' => $organization->id]);

    OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);
    OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($employee)
        ->get(route('my.overtime-requests.index'))
        ->assertInertia(fn ($page) => $page
            ->component('my/overtime-requests/index')
            ->has('requests.data', 1)
            ->where('requests.data.0.status', OvertimeRequestStatus::Pending->value));
});

// --- Permission seeding ---

test('the self-service overtime permissions are granted to the employee role', function () {
    expect(Permission::whereIn('name', ['RequestOwn:OvertimeAuthorization', 'ViewOwn:OvertimeAuthorization'])->count())
        ->toBe(2);
});
