<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // The approver's own note on a rejected leave (design-decisions.md
            // D-F5-d), distinct from the requester's `notes`. Null while
            // pending/approved, or when a rejection was left without one.
            $table->string('rejection_reason')->nullable()->after('approved_by');
        });
    }
};
