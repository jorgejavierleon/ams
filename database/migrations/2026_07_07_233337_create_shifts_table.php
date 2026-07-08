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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('fixed');
            $table->string('name');
            $table->text('description')->nullable();
            $table->time('tolerance_in')->nullable();
            $table->time('tolerance_out')->nullable();
            $table->boolean('work_on_holidays')->default(false);
            $table->boolean('is_archive')->default(false);
            $table->boolean('is_default')->default(false);
            $table->float('total_week_hours')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
