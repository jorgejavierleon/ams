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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('half_day')->default(false);
            $table->string('half_day_type')->nullable();
            // Decimal so half-day leaves can request 0.5 business days; this value
            // is deducted from the employee's vacation balance on approval.
            $table->decimal('business_days_requested', 4, 1);
            $table->string('status');
            $table->string('type');
            $table->string('medical_leave_number')->nullable();
            $table->string('medical_leave_doctor')->nullable();
            $table->string('notes')->nullable();
            // The admin who approved the request (null while pending/rejected).
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
