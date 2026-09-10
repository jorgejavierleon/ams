<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §12's open risk: a shift excess nobody decides on is not a neutral
     * non-event under the DT's criterio de realidad (art. 32) — it is the
     * employer having known and done nothing. This is the per-tenant number of
     * days a day may sit undecided before it is surfaced to HR (KOL-52).
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('overtime_pending_alert_threshold_days')->default(15);
        });
    }
};
