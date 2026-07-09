<?php

use App\Models\Holiday;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function holidayAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('holidays.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('holidays.index'))->assertForbidden();
});

test('non-admin users cannot create holidays', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)
        ->post(route('holidays.store'), [
            'name' => 'Año Nuevo',
            'date' => '2026-01-01',
            'mandatory' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('holidays', ['name' => 'Año Nuevo']);
});

// --- Index / visibility ---

test('admin sees official holidays plus their own, but not another org holidays', function () {
    $admin = holidayAdmin();
    $otherOrg = Organization::factory()->create();

    Holiday::factory()->create(['name' => 'Official', 'date' => '2026-01-01']);
    Holiday::factory()->forOrganization($admin->organization)->create([
        'name' => 'Mine',
        'date' => '2026-02-02',
    ]);
    Holiday::factory()->forOrganization($otherOrg)->create([
        'name' => 'Theirs',
        'date' => '2026-03-03',
    ]);

    $this->actingAs($admin)
        ->get(route('holidays.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('holidays/index')
                ->has('holidays.data', 2)
                ->where('holidays.data.0.name', 'Official')
                ->where('holidays.data.0.is_official', true)
                ->where('holidays.data.1.name', 'Mine')
                ->where('holidays.data.1.is_official', false),
        );
});

test('holidays index is sorted by date ascending by default', function () {
    Holiday::factory()->create(['date' => '2026-09-18', 'name' => 'Later']);
    Holiday::factory()->create(['date' => '2026-01-01', 'name' => 'Earlier']);

    $this->actingAs(holidayAdmin())
        ->get(route('holidays.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('holidays.data.0.name', 'Earlier')
                ->where('holidays.data.1.name', 'Later')
                ->where('filters.sort', 'date')
                ->where('filters.direction', 'asc'),
        );
});

test('holidays index can be searched by name', function () {
    Holiday::factory()->create(['name' => 'Fiestas Patrias', 'date' => '2026-09-18']);
    Holiday::factory()->create(['name' => 'Navidad', 'date' => '2026-12-25']);

    $this->actingAs(holidayAdmin())
        ->get(route('holidays.index', ['search' => 'Fiestas']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('holidays.data', 1)
                ->where('holidays.data.0.name', 'Fiestas Patrias'),
        );
});

test('holidays index ignores a disallowed sort column and falls back to the default', function () {
    Holiday::factory()->create(['date' => '2026-09-18', 'name' => 'Later']);
    Holiday::factory()->create(['date' => '2026-01-01', 'name' => 'Earlier']);

    $this->actingAs(holidayAdmin())
        ->get(route('holidays.index', ['sort' => 'id', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('filters.sort', 'date')
                ->where('filters.direction', 'asc')
                ->where('holidays.data.0.name', 'Earlier'),
        );
});

// --- Create ---

test('admin can create a holiday scoped to their organization', function () {
    $admin = holidayAdmin();

    $this->actingAs($admin)
        ->post(route('holidays.store'), [
            'name' => 'Aniversario',
            'date' => '2026-06-15',
            'mandatory' => false,
        ])
        ->assertRedirect(route('holidays.index'));

    $this->assertDatabaseHas('holidays', [
        'name' => 'Aniversario',
        'date' => '2026-06-15',
        'mandatory' => false,
        'organization_id' => $admin->organization_id,
    ]);
});

test('creating a holiday requires name, date and mandatory flag', function () {
    $this->actingAs(holidayAdmin())
        ->post(route('holidays.store'), [])
        ->assertSessionHasErrors(['name', 'date', 'mandatory']);
});

test('a holiday date must be unique within the organization', function () {
    $admin = holidayAdmin();
    Holiday::factory()->forOrganization($admin->organization)->create(['date' => '2026-06-15']);

    $this->actingAs($admin)
        ->post(route('holidays.store'), [
            'name' => 'Duplicate',
            'date' => '2026-06-15',
            'mandatory' => true,
        ])
        ->assertSessionHasErrors('date');
});

test('an organization may add a holiday on a date used by an official holiday', function () {
    $admin = holidayAdmin();
    Holiday::factory()->create(['date' => '2026-09-18']);

    $this->actingAs($admin)
        ->post(route('holidays.store'), [
            'name' => 'Our own celebration',
            'date' => '2026-09-18',
            'mandatory' => false,
        ])
        ->assertRedirect(route('holidays.index'));

    $this->assertDatabaseHas('holidays', [
        'name' => 'Our own celebration',
        'organization_id' => $admin->organization_id,
    ]);
});

// --- Update ---

test('admin can update their own holiday', function () {
    $admin = holidayAdmin();
    $holiday = Holiday::factory()->forOrganization($admin->organization)->create([
        'name' => 'Old name',
        'date' => '2026-01-01',
        'mandatory' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('holidays.update', $holiday), [
            'name' => 'New name',
            'mandatory' => false,
        ])
        ->assertRedirect(route('holidays.index'));

    $fresh = $holiday->fresh();
    expect($fresh->name)->toBe('New name');
    expect($fresh->mandatory)->toBeFalse();
    expect($fresh->date->format('Y-m-d'))->toBe('2026-01-01');
});

test('admin cannot update an official holiday', function () {
    $admin = holidayAdmin();
    $official = Holiday::factory()->create(['name' => 'Año Nuevo']);

    $this->actingAs($admin)
        ->patch(route('holidays.update', $official), [
            'name' => 'Hacked',
            'mandatory' => false,
        ])
        ->assertForbidden();

    expect($official->fresh()->name)->toBe('Año Nuevo');
});

test('admin cannot update a holiday from another organization', function () {
    $admin = holidayAdmin();
    $foreign = Holiday::factory()->forOrganization(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->patch(route('holidays.update', $foreign), [
            'name' => 'Hacked',
            'mandatory' => false,
        ])
        ->assertNotFound();
});

// --- Delete ---

test('admin can delete their own holiday', function () {
    $admin = holidayAdmin();
    $holiday = Holiday::factory()->forOrganization($admin->organization)->create();

    $this->actingAs($admin)
        ->delete(route('holidays.destroy', $holiday))
        ->assertRedirect(route('holidays.index'));

    $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
});

test('admin cannot delete an official holiday', function () {
    $admin = holidayAdmin();
    $official = Holiday::factory()->create();

    $this->actingAs($admin)
        ->delete(route('holidays.destroy', $official))
        ->assertForbidden();

    $this->assertDatabaseHas('holidays', ['id' => $official->id]);
});
