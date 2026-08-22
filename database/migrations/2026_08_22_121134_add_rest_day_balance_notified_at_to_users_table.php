<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KOL-48 (Resolución 38 art. 45.3): the last time this employee was
     * mailed their rest-day balance and its expiry, so the scheduled
     * notifier can space alerts 30 days apart per employee — not from a
     * global run date — and a same-day re-run never mails them twice.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('rest_day_balance_notified_at')->nullable()->after('overtime_rest_day_eligible');
        });
    }
};
