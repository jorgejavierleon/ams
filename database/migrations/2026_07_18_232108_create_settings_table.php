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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Attendance notification toggles: whether the platform emails on a
            // missing clock-in / clock-out, split between the employee and the
            // employer (admin) recipient.
            $table->boolean('employee_missing_in_notification')->default(true);
            $table->boolean('employee_missing_out_notification')->default(true);
            $table->boolean('employer_missing_in_notification')->default(true);
            $table->boolean('employer_missing_out_notification')->default(true);

            // Whether an employee is notified when their leave is approved.
            $table->boolean('leave_approval_notification')->default(true);

            // Document defaults: whether electronic signing is enabled at all,
            // and whether new documents default to ordered (sequential) signing.
            $table->boolean('documents_signature_enabled')->default(false);
            $table->boolean('documents_require_ordered_signing')->default(false);

            $table->timestamps();

            // One settings row per organization.
            $table->unique('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
