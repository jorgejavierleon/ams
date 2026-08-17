<?php

use App\Models\OvertimeRestDayBalance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ledger that makes {@see OvertimeRestDayBalance}'s
     * `consumed_hours` explainable (KOL-47 AC #2): every decrement is its own
     * row, pointing back at the specific accrual line it was drawn from, so
     * "why is this balance lower than it was accrued" always has an answer
     * on file rather than only a running total.
     */
    public function up(): void
    {
        Schema::create('overtime_rest_day_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('overtime_rest_day_balance_id')
                ->constrained(indexName: 'overtime_rest_day_consumptions_balance_id_foreign')
                ->cascadeOnDelete();

            $table->time('hours');
            $table->date('consumed_on');
            $table->text('note')->nullable();

            // Who registered the consumption. HR/admin today; nullable so a
            // future self-service request flow is not blocked by this schema.
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'overtime_rest_day_balance_id'], 'overtime_rest_day_consumptions_org_balance_idx');
        });
    }
};
