<?php

use App\Enums\OvertimePactStatus;
use App\Models\OvertimePact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The *pacto de horas extraordinarias* of PRD §7.6 — the written agreement
     * Código del Trabajo art. 32 requires before overtime is agreed for a
     * transitory need, valid at most three months and renewable.
     *
     * Renewal is a new row, never an extended `end_date`: the point of this
     * table is evidence of what was agreed when, and extending in place would
     * destroy exactly that.
     *
     * @see OvertimePact
     */
    public function up(): void
    {
        Schema::create('overtime_pacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->string('status')->default(OvertimePactStatus::Active->value);

            // Idempotency for the near-expiry alert (AC #3): set the first time
            // a notification goes out for this pact, so the scheduled command
            // never sends a second one for the same agreement.
            $table->timestamp('expiry_notified_at')->nullable();

            $table->timestamps();

            // Resolving the pact covering a worked date (KOL-42 AC #4) is an
            // employee-and-range lookup, done at every approval.
            $table->index(['organization_id', 'user_id', 'start_date', 'end_date']);
        });
    }
};
