<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The *código contable* the client uses for this company in their own
 * accounting system. It is the identifier the payroll reports segment and
 * export by, so it has to survive the round trip to the client's payroll
 * software unchanged.
 *
 * Nullable: existing companies have none, and a client with no formal
 * accounting catalogue never needs one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('code')->nullable()->after('social_reason');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
