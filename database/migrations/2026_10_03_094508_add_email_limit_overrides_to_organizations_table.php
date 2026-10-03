<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-organization monthly email limit overrides (KOL-137.2). Null means
     * "never overridden": the effective limit falls back to
     * active_users_count × the platform baseline, recalculated live.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('soft_email_limit_override')->nullable()->after('plan');
            $table->unsignedInteger('hard_email_limit_override')->nullable()->after('soft_email_limit_override');
        });
    }
};
