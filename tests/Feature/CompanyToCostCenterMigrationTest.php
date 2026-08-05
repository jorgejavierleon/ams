<?php

use App\Models\Organization;
use App\Models\Premise;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * KOL-32 collapses each organization onto a single company. This exercises the
 * data migration that gets a pre-KOL-32 tenant there: extra companies become
 * cost centres, their employees keep working, and no row is destroyed.
 *
 * The suite runs against an already-migrated schema, so each test first rewinds
 * `companies` to its pre-KOL-32 shape (accounting code present, no unique index
 * on the tenant) before replaying the two migrations under test.
 */
function rewindCompaniesToPreKol32(): void
{
    Schema::table('companies', function (Blueprint $table) {
        $table->dropUnique(['live_organization_id']);
        $table->dropColumn('live_organization_id');
        $table->string('code')->nullable()->after('social_reason');
    });
}

function replayKol32Migrations(): void
{
    $base = database_path('migrations');

    (require $base.'/2026_08_05_105650_convert_extra_companies_to_cost_centers.php')->up();
    (require $base.'/2026_08_05_105700_constrain_companies_to_one_per_organization.php')->up();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function legacyCompany(Organization $organization, string $socialReason, ?string $code = null): int
{
    return (int) DB::table('companies')->insertGetId([
        'organization_id' => $organization->id,
        'rut' => fake()->unique()->numerify('7#######-1'),
        'social_reason' => $socialReason,
        'code' => $code,
        'business_line' => 'Servicios',
        'email' => fake()->unique()->companyEmail(),
        'country' => 'Chile',
        'address' => 'Av. Siempre Viva 742',
        'phone' => '+56911111111',
        'company_type' => 'SpA',
        'is_est' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('extra companies become cost centres and their employees keep working', function () {
    rewindCompaniesToPreKol32();

    $organization = Organization::factory()->create();
    $retained = legacyCompany($organization, 'Empleador Legal SpA');
    $extra = legacyCompany($organization, 'Centro Norte', 'CC-002');

    $employee = User::factory()->create([
        'organization_id' => $organization->id,
        'company_id' => $extra,
    ]);

    replayKol32Migrations();

    // The extra company is now a cost centre carrying its name and code.
    $this->assertDatabaseHas('cost_centers', [
        'organization_id' => $organization->id,
        'name' => 'Centro Norte',
        'code' => 'CC-002',
    ]);

    // The employee moved onto the retained employer and onto that cost centre.
    $costCenterId = DB::table('cost_centers')->where('name', 'Centro Norte')->value('id');

    expect($employee->fresh()->company_id)->toBe($retained);
    expect($employee->fresh()->cost_center_id)->toBe((int) $costCenterId);
});

test('the retained company accounting code survives as a cost centre', function () {
    rewindCompaniesToPreKol32();

    $organization = Organization::factory()->create();
    $retained = legacyCompany($organization, 'Empleador Legal SpA', 'CC-001');

    $employee = User::factory()->create([
        'organization_id' => $organization->id,
        'company_id' => $retained,
    ]);

    replayKol32Migrations();

    $this->assertDatabaseHas('cost_centers', [
        'organization_id' => $organization->id,
        'name' => 'Empleador Legal SpA',
        'code' => 'CC-001',
    ]);

    expect($employee->fresh()->cost_center_id)->not->toBeNull();
    expect($employee->fresh()->company_id)->toBe($retained);
});

test('a retired company row is preserved, not destroyed', function () {
    rewindCompaniesToPreKol32();

    $organization = Organization::factory()->create();
    legacyCompany($organization, 'Empleador Legal SpA');
    $extra = legacyCompany($organization, 'Centro Norte');

    replayKol32Migrations();

    $row = DB::table('companies')->where('id', $extra)->first();

    // Every field survives, tenant link included: the row is only soft-deleted,
    // so the retired employer stays auditable.
    expect($row)->not->toBeNull();
    expect($row->social_reason)->toBe('Centro Norte');
    expect($row->rut)->not->toBeNull();
    expect($row->organization_id)->toBe($organization->id);
    expect($row->deleted_at)->not->toBeNull();
});

test('premises of an extra company move to the retained employer', function () {
    rewindCompaniesToPreKol32();

    $organization = Organization::factory()->create();
    $retained = legacyCompany($organization, 'Empleador Legal SpA');
    $extra = legacyCompany($organization, 'Centro Norte');

    $premise = Premise::factory()->create([
        'organization_id' => $organization->id,
        'company_id' => $extra,
    ]);

    replayKol32Migrations();

    expect($premise->fresh()->company_id)->toBe($retained);
});

test('two extra companies sharing an accounting code do not abort the migration', function () {
    rewindCompaniesToPreKol32();

    $organization = Organization::factory()->create();
    legacyCompany($organization, 'Empleador Legal SpA');
    legacyCompany($organization, 'Centro Norte', 'CC-DUP');
    legacyCompany($organization, 'Centro Sur', 'CC-DUP');

    replayKol32Migrations();

    // Both become cost centres; the second gives up the duplicated code rather
    // than colliding on the unique index.
    expect(DB::table('cost_centers')->where('organization_id', $organization->id)->count())->toBe(2);
    expect(DB::table('cost_centers')->where('code', 'CC-DUP')->count())->toBe(1);
    expect(DB::table('cost_centers')->where('name', 'Centro Sur')->exists())->toBeTrue();
});

test('each organization keeps its own retained employer', function () {
    rewindCompaniesToPreKol32();

    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    $firstRetained = legacyCompany($first, 'Primera SpA');
    legacyCompany($first, 'Primera Extra');
    $secondRetained = legacyCompany($second, 'Segunda SpA');

    replayKol32Migrations();

    expect(DB::table('companies')->where('organization_id', $first->id)->whereNull('deleted_at')->count())->toBe(1);
    expect(DB::table('companies')->where('organization_id', $first->id)->whereNull('deleted_at')->value('id'))->toBe($firstRetained);
    expect(DB::table('companies')->where('organization_id', $second->id)->whereNull('deleted_at')->value('id'))->toBe($secondRetained);
});

test('the unique index is in place after the migration', function () {
    rewindCompaniesToPreKol32();

    $organization = Organization::factory()->create();
    legacyCompany($organization, 'Empleador Legal SpA');

    replayKol32Migrations();

    expect(fn () => legacyCompany($organization, 'Second Employer'))
        ->toThrow(QueryException::class);
});
