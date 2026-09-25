<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

/**
 * An organization with an Owner, wired the same way OrganizationOwnershipTest
 * does: the user must exist before the organization can reference it as
 * owner_id, then gets its organization_id backfilled.
 */
function ownedOrganization(): array
{
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);
    $owner->assignRole('admin');

    return [$organization, $owner];
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('settings-organization.edit'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('settings-organization.edit'))
        ->assertForbidden();
});

// --- Edit ---

test('the settings page shows the current Owner', function () {
    [$organization, $owner] = ownedOrganization();

    $this->actingAs($owner)
        ->get(route('settings-organization.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/organization')
            ->where('owner.id', $owner->id)
            ->where('isOwner', true)
        );
});

test('a non-owner admin sees the current Owner but is not marked as Owner', function () {
    [$organization, $owner] = ownedOrganization();
    $otherAdmin = User::factory()->create(['organization_id' => $organization->id]);
    $otherAdmin->assignRole('admin');

    $this->actingAs($otherAdmin)
        ->get(route('settings-organization.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('owner.id', $owner->id)
            ->where('isOwner', false)
        );
});

// --- Transfer ---

test('the Owner can transfer ownership to another active user in the organization', function () {
    [$organization, $owner] = ownedOrganization();
    $newOwner = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);

    $this->actingAs($owner)
        ->patch(route('settings-organization.transfer-ownership'), ['user_id' => $newOwner->id])
        ->assertRedirect();

    expect($organization->fresh()->owner_id)->toBe($newOwner->id);
    expect($owner->fresh()->isOwner())->toBeFalse();
    expect($newOwner->fresh()->isOwner())->toBeTrue();
});

test('a non-owner admin cannot initiate a transfer', function () {
    [$organization, $owner] = ownedOrganization();
    $otherAdmin = User::factory()->create(['organization_id' => $organization->id]);
    $otherAdmin->assignRole('admin');
    $target = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);

    $this->actingAs($otherAdmin)
        ->patch(route('settings-organization.transfer-ownership'), ['user_id' => $target->id])
        ->assertForbidden();

    expect($organization->fresh()->owner_id)->toBe($owner->id);
});

test('a transfer to a user outside the organization is rejected', function () {
    [$organization, $owner] = ownedOrganization();
    $otherOrganization = Organization::factory()->create();
    $outsider = User::factory()->create(['organization_id' => $otherOrganization->id, 'is_active' => true]);

    $this->actingAs($owner)
        ->patch(route('settings-organization.transfer-ownership'), ['user_id' => $outsider->id])
        ->assertSessionHasErrors('user_id');

    expect($organization->fresh()->owner_id)->toBe($owner->id);
});
