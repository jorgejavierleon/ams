<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
