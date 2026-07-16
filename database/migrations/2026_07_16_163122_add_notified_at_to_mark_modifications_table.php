<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record when the employee was actually notified of the correction. The
     * 48h opposition window (Resolución 38, art. 40 c) is measured from the
     * moment the email is sent, not from when the request row was created, so
     * a lagging mail queue never shortens the worker's time to object.
     */
    public function up(): void
    {
        Schema::table('mark_modifications', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('mark_modifications', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
