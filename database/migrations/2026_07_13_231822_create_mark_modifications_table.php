<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A request to modify (or add) an attendance mark on a workday. Pending
     * modifications flag the workday for HR review; approving one rewrites the
     * underlying mark and triggers a workday recalculation.
     */
    public function up(): void
    {
        Schema::create('mark_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mark_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('date_time');
            $table->string('mark_type')->nullable();
            $table->text('notes')->nullable();
            $table->ulid()->unique();
            $table->timestamps();

            // The workday view lists modifications and flags pending ones.
            $table->index(['workday_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mark_modifications');
    }
};
