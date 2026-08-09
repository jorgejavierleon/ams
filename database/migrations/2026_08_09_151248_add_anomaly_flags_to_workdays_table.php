<?php

use App\Enums\AnomalyFlagReason;
use App\Services\WorkdayCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD §7.4: a day whose underlying data is not trustworthy enough to pay
     * from is flagged rather than paid, and the flag blocks it from reaching
     * `approved` until a human has looked at it — advisory at the point of
     * entry, blocking only at the point of approval (Resolución 38 art. 45.2).
     *
     * `anomaly_flags` holds the {@see AnomalyFlagReason} values for the day, or
     * null when none apply. It is written by {@see WorkdayCalculator} on every
     * pass, the same as every other computed column, so a flag whose cause is
     * corrected (a mark fixed, a shift assigned) disappears on the next
     * recalculation without anyone having to clear it by hand.
     */
    public function up(): void
    {
        Schema::table('workdays', function (Blueprint $table) {
            $table->json('anomaly_flags')->nullable()->after('overtime_calculated_at');
        });
    }
};
