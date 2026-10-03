<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per organization per calendar month per threshold crossed
     * (KOL-137.3). Existence of a row is the alert itself and the dedup key:
     * the unique index guarantees a crossing is only ever recorded once per
     * organization/type/month, however many send attempts hit it that month.
     */
    public function up(): void
    {
        Schema::create('email_limit_crossings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('month');
            $table->timestamps();

            $table->unique(['organization_id', 'type', 'month']);
        });
    }
};
