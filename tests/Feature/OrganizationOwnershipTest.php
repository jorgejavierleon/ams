<?php

use App\Actions\TransferOrganizationOwnership;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * KOL-133.1 adds Organization.owner_id and backfills it from existing data.
 * The suite runs against an already-migrated (empty) schema, so each backfill
 * test first rewinds owner_id and replays the migration against fixtures
 * created for that test.
 */
function rewindOwnerColumn(): void
{
    Schema::table('organizations', function (Blueprint $table) {
        $table->dropForeign(['owner_id']);
        $table->dropColumn('owner_id');
    });
}

function replayOwnerMigration(): void
{
    (require database_path('migrations/2026_09_25_120000_add_owner_id_to_organizations_table.php'))->up();
}

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

test('the migration backfills the earliest-created admin as owner', function () {
    rewindOwnerColumn();

    $organization = Organization::factory()->create();

    $laterAdmin = User::factory()->create([
        'organization_id' => $organization->id,
        'created_at' => now()->subDays(1),
    ]);
    $laterAdmin->assignRole('admin');

    $earliestAdmin = User::factory()->create([
        'organization_id' => $organization->id,
        'created_at' => now()->subDays(5),
    ]);
    $earliestAdmin->assignRole('admin');

    replayOwnerMigration();

    expect($organization->fresh()->owner_id)->toBe($earliestAdmin->id);
});

test('the migration leaves owner_id null for an organization with no admin user', function () {
    rewindOwnerColumn();

    $organization = Organization::factory()->create();

    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $employee->assignRole('employee');

    replayOwnerMigration();

    expect($organization->fresh()->owner_id)->toBeNull();
});

test('the migration does not assign an owner across organizations', function () {
    rewindOwnerColumn();

    $withAdmin = Organization::factory()->create();
    $withoutAdmin = Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $withAdmin->id]);
    $admin->assignRole('admin');

    User::factory()->create(['organization_id' => $withoutAdmin->id]);

    replayOwnerMigration();

    expect($withAdmin->fresh()->owner_id)->toBe($admin->id);
    expect($withoutAdmin->fresh()->owner_id)->toBeNull();
});

test('isOwner is true only for the organization Owner', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);

    $nonOwnerAdmin = User::factory()->create(['organization_id' => $organization->id]);
    $nonOwnerAdmin->assignRole('admin');

    $otherOwner = User::factory()->create();
    $otherOrganization = Organization::factory()->ownedBy($otherOwner)->create();
    $otherOwner->update(['organization_id' => $otherOrganization->id]);

    expect($owner->fresh()->isOwner())->toBeTrue();
    expect($nonOwnerAdmin->fresh()->isOwner())->toBeFalse();
    expect($otherOwner->fresh()->isOwner())->toBeTrue();
});

test('isOwner is false for a user with no organization', function () {
    $user = User::factory()->create(['organization_id' => null]);

    expect($user->isOwner())->toBeFalse();
});

test('the ownedBy factory state sets the organization owner', function () {
    $owner = User::factory()->create();

    $organization = Organization::factory()->ownedBy($owner)->create();

    expect($organization->owner_id)->toBe($owner->id);
    expect($organization->owner->id)->toBe($owner->id);
});

test('TransferOrganizationOwnership atomically moves ownership to another active user in the organization', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);

    $newOwner = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);

    app(TransferOrganizationOwnership::class)->handle($organization, $newOwner);

    $organization->refresh();
    expect($organization->owner_id)->toBe($newOwner->id);
    expect($owner->fresh()->isOwner())->toBeFalse();
    expect($newOwner->fresh()->isOwner())->toBeTrue();
});

test('TransferOrganizationOwnership rejects a target user outside the organization', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);

    $otherOrganization = Organization::factory()->create();
    $outsider = User::factory()->create(['organization_id' => $otherOrganization->id, 'is_active' => true]);

    expect(fn () => app(TransferOrganizationOwnership::class)->handle($organization, $outsider))
        ->toThrow(ValidationException::class);

    expect($organization->fresh()->owner_id)->toBe($owner->id);
});

test('TransferOrganizationOwnership rejects an inactive target user', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);

    $inactive = User::factory()->create(['organization_id' => $organization->id, 'is_active' => false]);

    expect(fn () => app(TransferOrganizationOwnership::class)->handle($organization, $inactive))
        ->toThrow(ValidationException::class);

    expect($organization->fresh()->owner_id)->toBe($owner->id);
});

test('TransferOrganizationOwnership rejects transferring to the current owner', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);

    expect(fn () => app(TransferOrganizationOwnership::class)->handle($organization, $owner))
        ->toThrow(ValidationException::class);
});

// --- Gate::before bypass moves from the admin role to the Owner (KOL-133.3) ---

test('the Owner bypasses authorization checks even without the admin role or any permission', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);

    // LeavePolicy::viewTeam requires the ViewTeam:Leave permission, which
    // this Owner holds no role — let alone that permission — for.
    expect($owner->can('viewTeam', Leave::class))->toBeTrue();
});

test('the Owner retains access even after the admin role is stripped of all its permissions', function () {
    $this->seed(RoleSeeder::class);

    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);
    $owner->assignRole('admin');

    Role::where('name', 'admin')->first()->syncPermissions([]);

    expect($owner->fresh()->can('viewTeam', Leave::class))->toBeTrue();
});

test('a non-owner admin no longer bypasses authorization checks its role holds no permission for', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    expect($admin->isOwner())->toBeFalse()
        ->and($admin->can('viewTeam', Leave::class))->toBeFalse();
});
