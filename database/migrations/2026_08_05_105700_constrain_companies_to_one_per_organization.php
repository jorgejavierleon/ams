<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce "one organization, one employer" in the schema rather than trusting
 * the controller, and drop `companies.code` now that the *código contable*
 * lives on `cost_centers` (moved by the previous migration).
 *
 * A plain unique index on `organization_id` would not work: `companies` is
 * soft-deleted, and a retired employer row keeps its tenant link, so it would
 * collide with the live one. The index is therefore on a generated column that
 * mirrors `organization_id` only while the row is live and is NULL once it is
 * soft-deleted — and MySQL treats NULLs in a unique index as distinct, so any
 * number of retired rows may coexist with the single live one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        // VIRTUAL, not STORED: adding a stored generated column derived from a
        // foreign-key column rebuilds the table and MySQL rejects it with
        // errno 1215. A virtual column still supports the unique index below.
        DB::statement(
            'ALTER TABLE companies ADD COLUMN live_organization_id BIGINT UNSIGNED'
            .' GENERATED ALWAYS AS (IF(deleted_at IS NULL, organization_id, NULL)) VIRTUAL'
        );

        Schema::table('companies', function (Blueprint $table) {
            $table->unique('live_organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['live_organization_id']);
            $table->dropColumn('live_organization_id');
            $table->string('code')->nullable()->after('social_reason');
        });
    }
};
