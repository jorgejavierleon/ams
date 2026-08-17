<?php

use App\Models\OvertimeAuthorization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KOL-47 (corrected): a standing per-employee eligibility flag, not a
     * property of any one pacto or agreement. When true, whoever approves
     * this employee's overtime may choose to compensate it in rest days
     * instead of payment (Código del Trabajo art. 32 §4); the choice is made
     * per record at approval time by {@see OvertimeAuthorization::approve()}.
     * When false, payment is the only option, unconditionally.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('overtime_rest_day_eligible')->default(false)->after('has_additional_sundays');
        });
    }
};
