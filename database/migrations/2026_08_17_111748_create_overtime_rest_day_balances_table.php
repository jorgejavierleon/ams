<?php

use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRestDayConsumption;
use App\Services\Overtime\RestDayBalanceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One accrual line per rest-day-compensated {@see OvertimeAuthorization}
     * (PRD closing note, KOL-47, Código del Trabajo art. 32 §4).
     *
     * `rest_hours` is the actual balance currency: the ratio the statute fixes
     * is "por cada hora extraordinaria corresponderá una hora y media de
     * feriado" — 1.5 hours of rest per overtime hour — so a day's approved
     * `final_hours` (kept here as `accrued_hours` for the audit trail) is not
     * itself the spendable amount. `consumed_hours` is decremented by
     * {@see OvertimeRestDayConsumption} rows, never negative.
     *
     * `expiry_date` is `accrual_date` plus six months, the statutory window
     * to use accrued rest before it must instead be paid. `expired_at` is
     * stamped once by the daily sweep the first time it finds an unconsumed
     * remainder past that date — see {@see RestDayBalanceService::sweepExpired()}.
     * The row is never deleted on expiry: it is the audit evidence of what
     * was accrued, and, once expired, of what must now be paid instead.
     */
    public function up(): void
    {
        Schema::create('overtime_rest_day_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // One accrual per approval: the source of truth for AC #2's
            // traceability, the record answers "which approved day produced
            // this balance" without a separate lookup table.
            $table->foreignId('overtime_authorization_id')->unique()->constrained()->cascadeOnDelete();

            $table->time('accrued_hours'); // The approved OT hours this line was born from.
            $table->time('rest_hours'); // accrued_hours * 1.5 — the spendable balance.
            $table->time('consumed_hours')->default('00:00:00');

            $table->date('accrual_date');
            $table->date('expiry_date');
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            // The consumption service's FIFO-by-expiry lookup, and the daily
            // sweep's "past expiry, still unconsumed" query.
            $table->index(['organization_id', 'user_id', 'expiry_date'], 'overtime_rest_day_balances_org_user_expiry_idx');
            $table->index(['organization_id', 'expiry_date', 'expired_at'], 'overtime_rest_day_balances_org_expiry_expired_idx');
        });
    }
};
