<?php

use App\Enums\OvertimeCalculationState;
use App\Jobs\CalculateOvertime;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the calculation engine may say about a day's overtime, and what it
     * may never say (PRD §7.2: *"Never writes directly to an approved state.
     * The output of this calculation can reach pending review at most."*).
     *
     * `overtime_state` is not the approval state machine — that one is
     * `pending | approved | objected` and belongs to the authorisation record of
     * PRD §8, which KOL-11 introduces. This column carries only what
     * {@see OvertimeCalculationState} can express, and that enum has no case for
     * an approved or otherwise payable day, so {@see CalculateOvertime} has no
     * value to write even if a future caller asked it to.
     *
     * The two `overtime_decided_*` columns are the other half of the same
     * guarantee, read from the opposite direction: they are where a human
     * decision lands, and the engine's write set excludes them by construction.
     * A recalculation therefore cannot erase a decision, and because the decided
     * figure is kept beside the recomputed one, a day whose value moved under an
     * existing decision reports itself as needing re-review rather than being
     * silently overwritten or silently kept.
     */
    public function up(): void
    {
        Schema::table('workdays', function (Blueprint $table) {
            // The engine's own output state. Null on a day never processed.
            $table->string('overtime_state')->nullable()->after('calculated_overtime');

            // When the engine last produced the figures above.
            $table->timestamp('overtime_calculated_at')->nullable()->after('overtime_state');

            // When a human last decided this day's overtime, and the figure they
            // decided on. Written by the review flow, never by the engine.
            $table->timestamp('overtime_decided_at')->nullable()->after('overtime_calculated_at');
            $table->time('overtime_decided_value')->nullable()->after('overtime_decided_at');

            // The supervisors' pending-overtime queue (PRD §7.5) reads exactly
            // this pair.
            $table->index(['organization_id', 'overtime_state']);
        });
    }
};
