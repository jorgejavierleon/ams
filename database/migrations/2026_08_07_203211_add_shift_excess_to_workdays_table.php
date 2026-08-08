<?php

use App\Services\Overtime\ShiftExcess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two shift excesses and the calculated overtime (OHC) they produce,
     * per PRD §7.2. Computed by {@see ShiftExcess}.
     *
     * These sit alongside `extra_time` rather than replacing it: `extra_time` is
     * span-minus-scheduled-duration and is read today by the Resolución 38 DT
     * reports, so changing its meaning would change what an inspector is shown.
     * Retiring it is a deliberate, separate exercise.
     *
     * All three are nullable and stay null when there is no basis to claim
     * overtime — no assigned shift, or only one mark. Null means "not computed",
     * which is a different statement from a zero excess and the one the anomaly
     * detection of KOL-40 needs to see.
     */
    public function up(): void
    {
        Schema::table('workdays', function (Blueprint $table) {
            // shift start − first mark, positive only.
            $table->time('pre_shift_excess')->nullable()->after('extra_time');

            // last mark − shift end, positive only.
            $table->time('post_shift_excess')->nullable()->after('pre_shift_excess');

            // post-shift excess, plus pre-shift excess when the tenant counts it.
            $table->time('calculated_overtime')->nullable()->after('post_shift_excess');
        });
    }
};
