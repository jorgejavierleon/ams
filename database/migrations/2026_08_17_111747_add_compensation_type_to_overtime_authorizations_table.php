<?php

use App\Enums\OvertimeCompensationType;
use App\Models\OvertimeAuthorization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KOL-47 AC #8: how this day's approved hours are compensated, resolved
     * and frozen at {@see OvertimeAuthorization::approve()} time
     * from the pacto covering the worked date. Payment by default — never
     * written by any other path, and never left null on an approved row.
     */
    public function up(): void
    {
        Schema::table('overtime_authorizations', function (Blueprint $table) {
            $table->string('compensation_type')->default(OvertimeCompensationType::Payment->value);
        });
    }
};
