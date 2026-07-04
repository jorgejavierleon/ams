<?php

use App\Models\Organization;
use App\Models\User;

uses()->group('saas');

function saasAdmin(): User
{
    return User::factory()->saasUser()->create();
}

// --- Access control ---

test('unauthenticated users are redirected to saas login', function () {
    $this->get(route('saas.organizations.index'))->assertRedirect('/saas/login');
});

test('non-saas users are denied access', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'saas')
        ->get(route('saas.organizations.index'))
        ->assertForbidden();
});

// --- Index ---

test('saas admin can list organizations', function () {
    Organization::factory()->count(3)->create();

    $this->actingAs(saasAdmin(), 'saas')
        ->get(route('saas.organizations.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('saas/organizations/index')
                ->has('organizations.data', 3)
        );
});

test('organizations index can be searched by name', function () {
    Organization::factory()->create(['name' => 'Acme Corporation']);
    Organization::factory()->create(['name' => 'Globex']);

    $this->actingAs(saasAdmin(), 'saas')
        ->get(route('saas.organizations.index', ['search' => 'Acme']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.name', 'Acme Corporation')
        );
});

test('organizations index defaults to sorting by name ascending and echoes filters', function () {
    Organization::factory()->create(['name' => 'Zeta']);
    Organization::factory()->create(['name' => 'Alpha']);

    $this->actingAs(saasAdmin(), 'saas')
        ->get(route('saas.organizations.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('organizations.data.0.name', 'Alpha')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
        );
});

test('organizations index can be sorted by name descending', function () {
    Organization::factory()->create(['name' => 'Alpha']);
    Organization::factory()->create(['name' => 'Zeta']);

    $this->actingAs(saasAdmin(), 'saas')
        ->get(route('saas.organizations.index', ['sort' => 'name', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('organizations.data.0.name', 'Zeta')
                ->where('filters.direction', 'desc')
        );
});

test('organizations index can be sorted by user count', function () {
    $few = Organization::factory()->create(['name' => 'Few']);
    $many = Organization::factory()->create(['name' => 'Many']);
    User::factory()->create(['organization_id' => $few->id]);
    User::factory()->count(3)->create(['organization_id' => $many->id]);

    $this->actingAs(saasAdmin(), 'saas')
        ->get(route('saas.organizations.index', ['sort' => 'users_count', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('organizations.data.0.name', 'Many')
                ->where('organizations.data.1.name', 'Few')
        );
});

test('organizations index ignores a disallowed sort column', function () {
    Organization::factory()->create(['name' => 'Beta']);
    Organization::factory()->create(['name' => 'Alpha']);

    $this->actingAs(saasAdmin(), 'saas')
        ->get(route('saas.organizations.index', ['sort' => 'password', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('organizations.data.0.name', 'Alpha')
        );
});

// --- Create ---

test('saas admin can view the create page', function () {
    $this->actingAs(saasAdmin(), 'saas')
        ->get(route('saas.organizations.create'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('saas/organizations/create')
                ->has('plans')
        );
});

test('saas admin can create an organization', function () {
    $this->actingAs(saasAdmin(), 'saas')
        ->post(route('saas.organizations.store'), [
            'name' => 'Acme Corporation',
            'slug' => 'acme',
            'plan' => 'pro',
        ])
        ->assertRedirect(route('saas.organizations.index'));

    $this->assertDatabaseHas('organizations', [
        'name' => 'Acme Corporation',
        'slug' => 'acme',
        'plan' => 'pro',
    ]);
});

test('creating an organization requires a unique slug', function () {
    Organization::factory()->create(['slug' => 'acme']);

    $this->actingAs(saasAdmin(), 'saas')
        ->post(route('saas.organizations.store'), [
            'name' => 'Acme Corporation',
            'slug' => 'acme',
            'plan' => 'pro',
        ])
        ->assertSessionHasErrors('slug');
});

test('creating an organization validates required fields', function () {
    $this->actingAs(saasAdmin(), 'saas')
        ->post(route('saas.organizations.store'), [])
        ->assertSessionHasErrors(['name', 'slug', 'plan']);
});

test('creating an organization rejects an invalid plan', function () {
    $this->actingAs(saasAdmin(), 'saas')
        ->post(route('saas.organizations.store'), [
            'name' => 'Acme Corporation',
            'slug' => 'acme',
            'plan' => 'enterprise',
        ])
        ->assertSessionHasErrors('plan');
});

// --- Edit / Update ---

test('saas admin can view the edit page', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(saasAdmin(), 'saas')
        ->get(route('saas.organizations.edit', $organization))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('saas/organizations/edit')
                ->where('organization.id', $organization->id)
                ->has('plans')
        );
});

test('saas admin can update an organization', function () {
    $organization = Organization::factory()->create(['name' => 'Old Name', 'plan' => 'free']);

    $this->actingAs(saasAdmin(), 'saas')
        ->patch(route('saas.organizations.update', $organization), [
            'name' => 'New Name',
            'slug' => $organization->slug,
            'plan' => 'basic',
        ])
        ->assertRedirect(route('saas.organizations.index'));

    expect($organization->fresh())
        ->name->toBe('New Name')
        ->and($organization->fresh()->plan->value)->toBe('basic');
});

test('updating an organization keeps its own slug valid', function () {
    $organization = Organization::factory()->create(['slug' => 'acme']);

    $this->actingAs(saasAdmin(), 'saas')
        ->patch(route('saas.organizations.update', $organization), [
            'name' => 'Acme Renamed',
            'slug' => 'acme',
            'plan' => 'free',
        ])
        ->assertRedirect(route('saas.organizations.index'));

    $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'name' => 'Acme Renamed']);
});

// --- Delete ---

test('an organization without users is hard-deleted', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(saasAdmin(), 'saas')
        ->delete(route('saas.organizations.destroy', $organization))
        ->assertRedirect(route('saas.organizations.index'));

    $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
});

test('an organization with users is soft-deleted', function () {
    $organization = Organization::factory()->create();
    User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs(saasAdmin(), 'saas')
        ->delete(route('saas.organizations.destroy', $organization))
        ->assertRedirect(route('saas.organizations.index'));

    $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
});
