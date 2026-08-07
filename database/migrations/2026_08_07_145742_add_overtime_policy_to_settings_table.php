<?php

use App\Enums\OvertimeAuthorizationMode;
use App\Enums\OvertimeCompensationType;
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
     * Defaults describe the least surprising organization: it does not run a
     * request flow yet, has signed no pactos, and pays its overtime — the
     * assumption Resolución 38 art. 43 makes when no written agreement says
     * otherwise.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Pre-authorisation, post-hoc, or both combined (PRD §7.1).
            $table->string('overtime_authorization_mode')
                ->default(OvertimeAuthorizationMode::PostHoc->value);

            // Whether a record needs a valid pacto behind it to be approvable.
            $table->boolean('overtime_requires_pact')->default(false);

            // Weekly volume above which the week is flagged as anomalous
            // (PRD §7.4). Per-tenant because a legitimate spike in a critical
            // IT or healthcare shift is not the same signal as in an office.
            $table->decimal('overtime_weekly_anomaly_threshold_hours', 5, 2)->default(10);

            // How many days back an employee may request overtime for in the
            // pre-authorisation flow.
            $table->unsignedSmallInteger('overtime_retroactive_request_days')->default(7);

            // Payment in payroll versus additional rest days (art. 32 inc. 4).
            $table->string('overtime_default_compensation_type')
                ->default(OvertimeCompensationType::Payment->value);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'overtime_authorization_mode',
                'overtime_requires_pact',
                'overtime_weekly_anomaly_threshold_hours',
                'overtime_retroactive_request_days',
                'overtime_default_compensation_type',
            ]);
        });
    }
};
