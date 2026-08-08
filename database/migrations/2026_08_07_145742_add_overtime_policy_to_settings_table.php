<?php

use App\Enums\OvertimeAuthorizationMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-tenant overtime policy (PRD §10). Every downstream calculation
     * reads these values, so they live on the existing per-organization
     * settings row rather than in a table of their own.
     *
     * Only genuine tenant policy lives here. Two keys that shipped with this
     * migration were removed again once their sources were read properly: how
     * overtime is compensated (KOL-56) and whether a pacto is mandatory to
     * approve it (KOL-57) are both settled by law, not by the employer. See
     * decision-1 — no configuration may make a worked hour unpayable.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Pre-authorisation, post-hoc, or both combined (PRD §7.1).
            $table->string('overtime_authorization_mode')
                ->default(OvertimeAuthorizationMode::PostHoc->value);

            // Weekly volume above which the week is flagged as anomalous
            // (PRD §7.4). Per-tenant because a legitimate spike in a critical
            // IT or healthcare shift is not the same signal as in an office.
            $table->decimal('overtime_weekly_anomaly_threshold_hours', 5, 2)->default(10);

            // How many days back an employee may request overtime for in the
            // pre-authorisation flow.
            $table->unsignedSmallInteger('overtime_retroactive_request_days')->default(7);

            // How overtime is compensated is deliberately absent: Resolución 38
            // art. 43 fixes payment as the fallback whenever no written pacto
            // says otherwise, and the pacto is per worker, so there is no
            // organization-level answer to store. See KOL-56 and KOL-47.
        });
    }
};
