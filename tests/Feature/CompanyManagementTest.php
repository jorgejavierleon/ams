<?php

use App\Models\Commune;
use App\Models\Company;
use App\Models\Organization;
use App\Models\Region;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function companyAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function validRut(int $body): string
{
    return $body.'-'.Rut::computeDv((string) $body);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function companyPayload(Region $region, Commune $commune, array $overrides = []): array
{
    return array_merge([
        'rut' => validRut(76000001),
        'social_reason' => 'Acme SpA',
        'business_line' => 'Construction',
        'email' => 'contact@acme.test',
        'region_id' => $region->id,
        'commune_id' => $commune->id,
        'address' => 'Av. Siempre Viva 742',
        'phone' => '+56911111111',
        'company_type' => 'SpA',
        'is_est' => false,
        'is_active' => true,
        'representatives' => [],
    ], $overrides);
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('companies.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('companies.index'))->assertForbidden();
});

// --- Index ---

test('admin can list companies with region, commune and employee count', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create(['name' => 'Región de Prueba']);
    $commune = Commune::factory()->create(['region_id' => $region->id, 'name' => 'Comuna Prueba']);
    $company = Company::factory()->create([
        'organization_id' => $admin->organization_id,
        'social_reason' => 'Acme SpA',
        'region_id' => $region->id,
        'commune_id' => $commune->id,
    ]);
    User::factory()->count(2)->create([
        'organization_id' => $admin->organization_id,
        'company_id' => $company->id,
    ]);

    $this->actingAs($admin)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('companies/index')
                ->has('companies.data', 1)
                ->where('companies.data.0.social_reason', 'Acme SpA')
                ->where('companies.data.0.region', 'Región de Prueba')
                ->where('companies.data.0.commune', 'Comuna Prueba')
                ->where('companies.data.0.users_count', 2),
        );
});

test('companies index only shows the current organization companies', function () {
    $admin = companyAdmin();
    Company::factory()->create(['organization_id' => $admin->organization_id, 'social_reason' => 'Mine']);
    Company::factory()->create(['social_reason' => 'Foreign']);

    $this->actingAs($admin)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.social_reason', 'Mine'),
        );
});

test('companies index can be searched by name or rut', function () {
    $admin = companyAdmin();
    Company::factory()->create(['organization_id' => $admin->organization_id, 'social_reason' => 'Alpha', 'rut' => validRut(76000002)]);
    Company::factory()->create(['organization_id' => $admin->organization_id, 'social_reason' => 'Beta', 'rut' => validRut(76000003)]);

    $this->actingAs($admin)
        ->get(route('companies.index', ['search' => 'Alph']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.social_reason', 'Alpha'),
        );
});

// --- Cascading communes endpoint ---

test('the communes endpoint returns only the communes for the given region', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    Commune::factory()->count(3)->create(['region_id' => $region->id]);
    Commune::factory()->create(); // belongs to another region

    $this->actingAs($admin)
        ->getJson(route('regions.communes', $region))
        ->assertOk()
        ->assertJsonCount(3);
});

// --- Create ---

test('admin can create a company with cascading region and commune', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $this->actingAs($admin)
        ->post(route('companies.store'), companyPayload($region, $commune, [
            'rut' => '76.111.111-6',
        ]))
        ->assertRedirect(route('companies.index'));

    $this->assertDatabaseHas('companies', [
        'social_reason' => 'Acme SpA',
        'organization_id' => $admin->organization_id,
        'region_id' => $region->id,
        'commune_id' => $commune->id,
        'rut' => Rut::normalize('76.111.111-6'),
    ]);
});

test('creating a company assigns representatives as company users', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $this->actingAs($admin)
        ->post(route('companies.store'), companyPayload($region, $commune, [
            'representatives' => [
                [
                    'rut' => validRut(15000001),
                    'first_name' => 'Ana',
                    'last_name' => 'Pérez',
                    'second_last_name' => 'Soto',
                    'email' => 'ana@acme.test',
                ],
                [
                    'rut' => validRut(15000002),
                    'first_name' => 'Luis',
                    'last_name' => 'Rojas',
                    'second_last_name' => '',
                    'email' => 'luis@acme.test',
                ],
            ],
        ]))
        ->assertRedirect(route('companies.index'));

    $company = Company::query()->firstOrFail();

    expect($company->representatives()->count())->toBe(2);

    $this->assertDatabaseHas('users', [
        'company_id' => $company->id,
        'organization_id' => $admin->organization_id,
        'name' => 'Ana Pérez',
        'rut' => validRut(15000001),
        'personal_email' => 'ana@acme.test',
        'is_legal_rep' => true,
    ]);
});

test('creating a company rejects an invalid rut', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $this->actingAs($admin)
        ->post(route('companies.store'), companyPayload($region, $commune, [
            'rut' => '12.345.678-9',
        ]))
        ->assertSessionHasErrors('rut');
});

test('creating a company rejects a commune outside the selected region', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(); // different region

    $this->actingAs($admin)
        ->post(route('companies.store'), companyPayload($region, $commune))
        ->assertSessionHasErrors('commune_id');
});

// --- Update ---

test('updating a company reconciles its representatives', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);
    $company = Company::factory()->create([
        'organization_id' => $admin->organization_id,
        'region_id' => $region->id,
        'commune_id' => $commune->id,
    ]);
    $existing = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'company_id' => $company->id,
        'is_legal_rep' => true,
        'rut' => validRut(15000010),
        'name' => 'Old Rep',
    ]);

    $this->actingAs($admin)
        ->patch(route('companies.update', $company), companyPayload($region, $commune, [
            'representatives' => [
                [
                    'rut' => validRut(15000011),
                    'first_name' => 'New',
                    'last_name' => 'Rep',
                    'second_last_name' => '',
                    'email' => 'new@acme.test',
                ],
            ],
        ]))
        ->assertRedirect(route('companies.index'));

    expect(User::query()->whereKey($existing->id)->exists())->toBeFalse();
    expect($company->representatives()->count())->toBe(1);
    $this->assertDatabaseHas('users', [
        'company_id' => $company->id,
        'rut' => validRut(15000011),
        'name' => 'New Rep',
    ]);
});

test('admin cannot edit a company from another organization', function () {
    $admin = companyAdmin();
    $foreign = Company::factory()->create();

    $this->actingAs($admin)
        ->get(route('companies.edit', $foreign))
        ->assertNotFound();
});

// --- Delete ---

test('a company is soft-deleted', function () {
    $admin = companyAdmin();
    $company = Company::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->delete(route('companies.destroy', $company))
        ->assertRedirect(route('companies.index'));

    $this->assertSoftDeleted('companies', ['id' => $company->id]);
});
