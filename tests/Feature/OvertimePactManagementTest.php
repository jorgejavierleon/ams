<?php

use App\Enums\OvertimePactStatus;
use App\Models\Organization;
use App\Models\OvertimePact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function overtimePactAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

// --- Access control (AC #5) ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('overtime.pacts.index'))->assertRedirect(route('login'));
});

test('an employee without Manage:OvertimeAuthorization is denied access', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $employee->assignRole('employee');

    $this->actingAs($employee)->get(route('overtime.pacts.index'))->assertForbidden();
});

test('a supervisor holding only team overtime permissions is denied access', function () {
    $organization = Organization::factory()->create();
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    $this->actingAs($supervisor)->get(route('overtime.pacts.index'))->assertForbidden();
});

// --- Index / tenant isolation (AC #6) ---

test('admin can list pactos with the employee name', function () {
    $admin = overtimePactAdmin();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Ana Soto']);
    OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
    ]);

    $this->actingAs($admin)
        ->get(route('overtime.pacts.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('overtime/pacts/index')
                ->has('pacts.data', 1)
                ->where('pacts.data.0.employee', 'Ana Soto')
                ->where('pacts.data.0.status.value', 'active'),
        );
});

test('a tenant never sees another tenant pactos', function () {
    $admin = overtimePactAdmin();
    OvertimePact::factory()->create(['organization_id' => $admin->organization_id]);
    OvertimePact::factory()->create();

    $this->actingAs($admin)
        ->get(route('overtime.pacts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('pacts.data', 1));
});

// --- Create (AC #1) ---

test('admin can create a pacto for an employee over a date range', function () {
    $admin = overtimePactAdmin();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->post(route('overtime.pacts.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-10-31',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('overtime_pacts', [
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'status' => OvertimePactStatus::Active->value,
    ]);
});

test('a range exactly three months long is accepted', function () {
    $admin = overtimePactAdmin();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->post(route('overtime.pacts.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-11-01',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('overtime_pacts', ['user_id' => $employee->id]);
});

test('a range longer than three months is refused with a Spanish message citing the reason', function () {
    $admin = overtimePactAdmin();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->post(route('overtime.pacts.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-11-02',
        ])
        ->assertSessionHasErrors([
            'end_date' => 'El pacto no puede tener una vigencia superior a tres meses (art. 32 del Código del Trabajo).',
        ]);

    $this->assertDatabaseMissing('overtime_pacts', ['user_id' => $employee->id]);
});

test('a pacto cannot be created for an employee of another organization', function () {
    $admin = overtimePactAdmin();
    $foreignEmployee = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('overtime.pacts.store'), [
            'user_id' => $foreignEmployee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ])
        ->assertSessionHasErrors('user_id');
});

// --- Renewal (AC #2) ---

test('renewal creates a new agreement rather than extending the existing one', function () {
    $admin = overtimePactAdmin();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);

    $original = OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-05-01',
        'end_date' => '2026-07-31',
    ]);

    $this->actingAs($admin)
        ->post(route('overtime.pacts.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-10-31',
        ])
        ->assertRedirect();

    expect(OvertimePact::where('user_id', $employee->id)->count())->toBe(2);
    expect($original->fresh()->start_date->toDateString())->toBe('2026-05-01')
        ->and($original->fresh()->end_date->toDateString())->toBe('2026-07-31');
});

// --- Update ---

test('admin can edit a pacto date range', function () {
    $admin = overtimePactAdmin();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $pact = OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);

    $this->actingAs($admin)
        ->put(route('overtime.pacts.update', $pact), [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-30',
        ])
        ->assertRedirect();

    expect($pact->fresh()->end_date->toDateString())->toBe('2026-09-30');
});

test('admin cannot update a pacto from another organization', function () {
    $admin = overtimePactAdmin();
    $foreign = OvertimePact::factory()->create();

    $this->actingAs($admin)
        ->put(route('overtime.pacts.update', $foreign), [
            'user_id' => $foreign->user_id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ])
        ->assertNotFound();
});

// --- Revoke ---

test('admin can revoke a pacto and the record is kept, not deleted', function () {
    $admin = overtimePactAdmin();
    $pact = OvertimePact::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->patch(route('overtime.pacts.revoke', $pact))
        ->assertRedirect();

    expect($pact->fresh()->status)->toBe(OvertimePactStatus::Revoked);
    $this->assertDatabaseHas('overtime_pacts', ['id' => $pact->id]);
});

// --- Activate ---

test('admin can reactivate a revoked pacto', function () {
    $admin = overtimePactAdmin();
    $pact = OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'status' => OvertimePactStatus::Revoked,
    ]);

    $this->actingAs($admin)
        ->patch(route('overtime.pacts.activate', $pact))
        ->assertRedirect();

    expect($pact->fresh()->status)->toBe(OvertimePactStatus::Active);
});

test('admin cannot reactivate a pacto from another organization', function () {
    $admin = overtimePactAdmin();
    $foreign = OvertimePact::factory()->create(['status' => OvertimePactStatus::Revoked]);

    $this->actingAs($admin)
        ->patch(route('overtime.pacts.activate', $foreign))
        ->assertNotFound();
});
