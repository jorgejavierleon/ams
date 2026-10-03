<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide configuration (KOL-137.2), exactly one row. Currently
     * holds only the "expected emails per user per month" baseline, left
     * nullable since no default is prescribed until an admin sets one.
     */
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('expected_emails_per_user_per_month')->nullable();
            $table->timestamps();
        });
    }
};
