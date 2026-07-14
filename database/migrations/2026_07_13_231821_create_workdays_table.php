<?php

use App\Services\WorkdayCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The workdays table holds one computed row per employee per day: the
     * derived attendance outcome (status, worked/extra/missing time, shift
     * deltas) rolled up by {@see WorkdayCalculator} from that
     * day's marks, scheduled shift and approved leaves.
     */
    public function up(): void
    {
        Schema::create('workdays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('premise_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mark_in_id')->nullable()->constrained(table: 'marks')->nullOnDelete();
            $table->foreignId('mark_out_id')->nullable()->constrained(table: 'marks')->nullOnDelete();
            $table->foreignId('leave_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('mark_in_at')->nullable();
            $table->timestamp('mark_out_at')->nullable();
            $table->time('shift_start_time')->nullable();
            $table->time('shift_end_time')->nullable();
            $table->time('in_time_difference')->nullable();
            $table->time('out_time_difference')->nullable();
            $table->time('worked_time')->nullable();
            $table->time('extra_time')->nullable();
            $table->time('missing_time')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            // One computed row per employee/day; listings filter by date first.
            $table->unique(['user_id', 'date']);
            $table->index(['organization_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workdays');
    }
};
