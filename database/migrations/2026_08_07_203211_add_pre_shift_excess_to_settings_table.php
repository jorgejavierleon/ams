<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether an early arrival counts towards the calculated overtime (PRD §7.2
     * and §10). Both shift excesses are stored on every workday regardless, so
     * this governs only what feeds OHC — flipping it is a configuration change,
     * never a recalculation of history.
     *
     * It defaults to off because art. 32 requires the employer's knowledge or
     * authorisation behind excess hours: an employee who decides alone to arrive
     * two hours early has not thereby earned overtime.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('overtime_counts_pre_shift_excess')->default(false);
        });
    }
};
