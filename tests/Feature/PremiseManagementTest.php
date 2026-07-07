<?php

use App\Models\Company;
use App\Models\Organization;
use App\Models\Premise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function premiseAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function premisePayload(Company $company, array $overrides = []): array
{
    return array_merge([
        'company_id' => $company->id,
        'name' => 'Sucursal Centro',
        'code' => 'SUC-001',
        'country' => 'Chile',
        'region' => 'Región Metropolitana',
        'commune' => 'Santiago',
        'address' => 'Av. Libertador 1234',
        'lat' => -33.44890000,
        'lng' => -70.66930000,
        'responsable_name' => 'Ana Pérez',
        'responsable_email' => 'ana@acme.test',
        'responsable_phone' => '+56911111111',
    ], $overrides);
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('premises.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('premises.index'))->assertForbidden();
});

// --- Index ---

test('admin can list premises with company and coordinate status', function () {
    $admin = premiseAdmin();
    $company = Company::factory()->create([
        'organization_id' => $admin->organization_id,
        'social_reason' => 'Acme SpA',
    ]);
    Premise::factory()->forCompany($company)->create([
        'name' => 'Sucursal Centro',
        'lat' => -33.4489,
        'lng' => -70.6693,
    ]);

    $this->actingAs($admin)
        ->get(route('premises.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('premises/index')
                ->has('premises.data', 1)
                ->where('premises.data.0.name', 'Sucursal Centro')
                ->where('premises.data.0.company', 'Acme SpA')
                ->where('premises.data.0.has_coordinates', true),
        );
});

test('premises index only shows the current organization premises', function () {
    $admin = premiseAdmin();
    Premise::factory()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Mine',
    ]);
    Premise::factory()->create(['name' => 'Foreign']);

    $this->actingAs($admin)
        ->get(route('premises.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('premises.data', 1)
                ->where('premises.data.0.name', 'Mine'),
        );
});

test('premises index can be searched by name', function () {
    $admin = premiseAdmin();
    Premise::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Alpha Branch']);
    Premise::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Beta Branch']);

    $this->actingAs($admin)
        ->get(route('premises.index', ['search' => 'Alpha']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('premises.data', 1)
                ->where('premises.data.0.name', 'Alpha Branch'),
        );
});

// --- Create ---

test('admin can create a premise with coordinates', function () {
    $admin = premiseAdmin();
    $company = Company::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->post(route('premises.store'), premisePayload($company))
        ->assertRedirect(route('premises.index'));

    $this->assertDatabaseHas('premises', [
        'name' => 'Sucursal Centro',
        'organization_id' => $admin->organization_id,
        'company_id' => $company->id,
        'lat' => -33.44890000,
        'lng' => -70.66930000,
    ]);
});

test('creating a premise validates required fields server-side', function () {
    $admin = premiseAdmin();

    $this->actingAs($admin)
        ->post(route('premises.store'), [])
        ->assertSessionHasErrors(['company_id', 'name']);
});

test('creating a premise rejects a company from another organization', function () {
    $admin = premiseAdmin();
    $foreignCompany = Company::factory()->create(); // different organization

    $this->actingAs($admin)
        ->post(route('premises.store'), premisePayload($foreignCompany))
        ->assertSessionHasErrors('company_id');
});

test('creating a premise rejects out-of-range coordinates', function () {
    $admin = premiseAdmin();
    $company = Company::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->post(route('premises.store'), premisePayload($company, [
            'lat' => 120,
            'lng' => 400,
        ]))
        ->assertSessionHasErrors(['lat', 'lng']);
});

test('a premise can be created without coordinates', function () {
    $admin = premiseAdmin();
    $company = Company::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->post(route('premises.store'), premisePayload($company, [
            'lat' => '',
            'lng' => '',
        ]))
        ->assertRedirect(route('premises.index'));

    $premise = Premise::query()->firstOrFail();

    expect($premise->lat)->toBeNull();
    expect($premise->lng)->toBeNull();
});

// --- Update ---

test('admin can update a premise', function () {
    $admin = premiseAdmin();
    $company = Company::factory()->create(['organization_id' => $admin->organization_id]);
    $premise = Premise::factory()->forCompany($company)->create(['name' => 'Old name']);

    $this->actingAs($admin)
        ->patch(route('premises.update', $premise), premisePayload($company, [
            'name' => 'New name',
            'lat' => -20.5,
            'lng' => -68.9,
        ]))
        ->assertRedirect(route('premises.index'));

    $this->assertDatabaseHas('premises', [
        'id' => $premise->id,
        'name' => 'New name',
        'lat' => -20.5,
        'lng' => -68.9,
    ]);
});

test('admin cannot edit a premise from another organization', function () {
    $admin = premiseAdmin();
    $foreign = Premise::factory()->create();

    $this->actingAs($admin)
        ->get(route('premises.edit', $foreign))
        ->assertNotFound();
});

// --- Delete ---

test('a premise with no active employees is soft-deleted', function () {
    $admin = premiseAdmin();
    $premise = Premise::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->delete(route('premises.destroy', $premise))
        ->assertRedirect(route('premises.index'));

    $this->assertSoftDeleted('premises', ['id' => $premise->id]);
});

test('a premise with active employees cannot be deleted', function () {
    $admin = premiseAdmin();
    $premise = Premise::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'premise_id' => $premise->id,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('premises.destroy', $premise));

    $this->assertDatabaseHas('premises', ['id' => $premise->id, 'deleted_at' => null]);
});

test('a premise with only inactive employees can be deleted', function () {
    $admin = premiseAdmin();
    $premise = Premise::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'premise_id' => $premise->id,
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->delete(route('premises.destroy', $premise))
        ->assertRedirect(route('premises.index'));

    $this->assertSoftDeleted('premises', ['id' => $premise->id]);
});
