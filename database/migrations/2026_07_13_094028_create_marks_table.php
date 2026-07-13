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
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('premise_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('original_date_time')->nullable();
            $table->dateTime('date_time');
            $table->time('shift_start_time')->nullable();
            $table->time('shift_end_time')->nullable();
            $table->string('type');
            // Immutable legal snapshot captured at punch time (Resolución 38).
            $table->string('employee_rut')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employer_rut')->nullable();
            $table->string('employer_name')->nullable();
            $table->string('premise_name')->nullable();
            $table->string('premise_address')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('checksum');
            $table->timestamps();
            $table->softDeletes();

            // Attendance is read per employee/day and verified by checksum.
            $table->index(['user_id', 'type', 'date_time']);
            $table->index('checksum');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marks');
    }
};
