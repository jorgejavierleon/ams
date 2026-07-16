<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot the mark's time as it stood when the correction was requested.
     * Approving a modification rewrites the underlying mark, so the original
     * time cannot be read back from it afterwards — the audit trail keeps its
     * own copy.
     */
    public function up(): void
    {
        Schema::table('mark_modifications', function (Blueprint $table) {
            $table->timestamp('original_date_time')->nullable()->after('date_time');
        });
    }

    public function down(): void
    {
        Schema::table('mark_modifications', function (Blueprint $table) {
            $table->dropColumn('original_date_time');
        });
    }
};
