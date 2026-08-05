<?php

use App\Enums\ContractType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The kind of engagement an employee works under (see {@see ContractType}).
 * Nullable with no backfill: employees created before this column predate the
 * distinction, and guessing "indefinido" for them would stamp a wrong contract
 * type on records that payroll reports filter by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contract_type')->nullable()->after('contract_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('contract_type');
        });
    }
};
