<?php

use App\Models\Commune;
use App\Models\Company;
use App\Models\Organization;
use App\Models\Region;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Database\QueryException;
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
    $this->get(route('company.edit'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('company.edit'))->assertForbidden();
});

// --- One employer per organization ---

test('the database refuses a second company for the same organization', function () {
    $organization = Organization::factory()->create();
    Company::factory()->create(['organization_id' => $organization->id]);

    expect(fn () => Company::factory()->create(['organization_id' => $organization->id]))
        ->toThrow(QueryException::class);
});

test('there is no route to create or delete a company', function () {
    expect(Route::has('companies.index'))->toBeFalse();
    expect(Route::has('companies.create'))->toBeFalse();
    expect(Route::has('companies.store'))->toBeFalse();
    expect(Route::has('companies.destroy'))->toBeFalse();
});

// --- Edit ---

test('the edit page shows the organization own company', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);
    Company::factory()->create([
        'organization_id' => $admin->organization_id,
        'social_reason' => 'Acme SpA',
        'region_id' => $region->id,
        'commune_id' => $commune->id,
    ]);

    // Another tenant's employer must stay invisible.
    Company::factory()->create(['social_reason' => 'Foreign SpA']);

    $this->actingAs($admin)
        ->get(route('company.edit'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('companies/edit')
                ->where('company.social_reason', 'Acme SpA')
                ->where('company.region_id', $region->id),
        );
});

test('the edit page renders an empty form when the organization has no company yet', function () {
    $admin = companyAdmin();

    $this->actingAs($admin)
        ->get(route('company.edit'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('companies/edit')
                ->where('company', null),
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

// --- Update ---

test('the first save creates the organization company', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $this->actingAs($admin)
        ->put(route('company.update'), companyPayload($region, $commune, [
            'rut' => '76.111.111-6',
        ]))
        ->assertRedirect(route('company.edit'));

    $this->assertDatabaseHas('companies', [
        'social_reason' => 'Acme SpA',
        'organization_id' => $admin->organization_id,
        'region_id' => $region->id,
        'commune_id' => $commune->id,
        'rut' => Rut::normalize('76.111.111-6'),
    ]);
});

test('saving twice updates the same company rather than creating a second one', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $this->actingAs($admin)
        ->put(route('company.update'), companyPayload($region, $commune))
        ->assertRedirect(route('company.edit'));

    $this->actingAs($admin)
        ->put(route('company.update'), companyPayload($region, $commune, [
            'social_reason' => 'Acme Renamed SpA',
        ]))
        ->assertRedirect(route('company.edit'));

    expect(Company::query()->where('organization_id', $admin->organization_id)->count())->toBe(1);
    expect(Company::query()->first()->social_reason)->toBe('Acme Renamed SpA');
});

test('an admin only ever edits their own organization company', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $foreign = Company::factory()->create(['social_reason' => 'Foreign SpA']);

    $this->actingAs($admin)
        ->put(route('company.update'), companyPayload($region, $commune))
        ->assertRedirect(route('company.edit'));

    // The other tenant's employer is untouched, and this one got its own row.
    expect($foreign->fresh()->social_reason)->toBe('Foreign SpA');
    expect(Company::query()->where('organization_id', $admin->organization_id)->count())->toBe(1);
});

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
        ->put(route('company.update'), companyPayload($region, $commune, [
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
        ->assertRedirect(route('company.edit'));

    expect(User::query()->whereKey($existing->id)->exists())->toBeFalse();
    expect($company->representatives()->count())->toBe(1);
    $this->assertDatabaseHas('users', [
        'company_id' => $company->id,
        'rut' => validRut(15000011),
        'name' => 'New Rep',
    ]);
});

test('saving a company assigns representatives as company users', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $this->actingAs($admin)
        ->put(route('company.update'), companyPayload($region, $commune, [
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
        ->assertRedirect(route('company.edit'));

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

// --- Validation ---

test('saving a company rejects an invalid rut', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $this->actingAs($admin)
        ->put(route('company.update'), companyPayload($region, $commune, [
            'rut' => '12.345.678-9',
        ]))
        ->assertSessionHasErrors('rut');
});

test('saving a company rejects a commune outside the selected region', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(); // different region

    $this->actingAs($admin)
        ->put(route('company.update'), companyPayload($region, $commune))
        ->assertSessionHasErrors('commune_id');
});

test('saving a company validates all required fields server-side', function () {
    $admin = companyAdmin();

    $this->actingAs($admin)
        ->put(route('company.update'), [])
        ->assertSessionHasErrors([
            'social_reason',
            'rut',
            'business_line',
            'email',
            'region_id',
            'commune_id',
            'address',
            'phone',
            'company_type',
        ]);
});

test('saving a company rejects an invalid email', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $this->actingAs($admin)
        ->put(route('company.update'), companyPayload($region, $commune, [
            'email' => 'not-an-email',
        ]))
        ->assertSessionHasErrors('email');
});

test('saving a company validates representative fields server-side', function () {
    $admin = companyAdmin();
    $region = Region::factory()->create();
    $commune = Commune::factory()->create(['region_id' => $region->id]);

    $this->actingAs($admin)
        ->put(route('company.update'), companyPayload($region, $commune, [
            'representatives' => [
                [
                    'rut' => 'invalid',
                    'first_name' => '',
                    'last_name' => '',
                    'second_last_name' => '',
                    'email' => 'nope',
                ],
            ],
        ]))
        ->assertSessionHasErrors([
            'representatives.0.rut',
            'representatives.0.first_name',
            'representatives.0.last_name',
            'representatives.0.email',
        ]);
});

// --- The accounting code no longer lives here ---

test('the company no longer carries an accounting code', function () {
    expect(Schema::hasColumn('companies', 'code'))->toBeFalse();
});
