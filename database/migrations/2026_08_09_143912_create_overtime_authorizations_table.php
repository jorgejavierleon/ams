<?php

use App\Enums\OvertimeAuthorizationStatus;
use App\Models\OvertimeAuthorization;
use App\Models\Workday;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The authorisation record of PRD §8 — the only row in the system from
     * which a payable overtime hour can be born.
     *
     * The calculation engine writes {@see Workday}, never this table: a day's
     * `calculated_overtime` is what an employee's marks imply, and that is a
     * different claim from what an employer owes. Nothing here is derived from
     * attendance at read time either — the export of KOL-49 reads this table and
     * only this table, so an hour nobody approved has no path into payroll.
     *
     * **The three figures are kept apart on purpose.** Collapsing OHC, OHR and
     * OHA into one number would make the final figure unexplainable: an
     * accountant asking why two hours and not three would have nothing to read.
     * Kept separately, the row answers on its own — calculated three, requested
     * two, authorised two.
     *
     * @see OvertimeAuthorization
     */
    public function up(): void
    {
        Schema::create('overtime_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            // The computed day these hours were worked on. Unique: a second
            // authorisation for the same day would be a second answer to the
            // same question, and the export would have to pick one.
            $table->foreignId('workday_id')->unique()->constrained()->cascadeOnDelete();

            // Employee and date are carried here rather than reached through
            // the workday. They are that row's identity and never change, so
            // there is nothing to drift, and the export and the reports of
            // KOL-13/KOL-24 select by employee and period without a join.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            // The three figures of the glossary, never collapsed. All nullable:
            // a null is "this tenant has no such figure" — a company not running
            // Mode A never produces an OHR — which is a different statement from
            // a zero, and KOL-46 excludes it from the MIN rather than flooring
            // the result to nothing.
            $table->time('calculated_hours')->nullable(); // OHC, snapshot of the engine's figure.
            $table->time('requested_hours')->nullable();  // OHR, Mode A only.
            $table->time('authorized_hours')->nullable(); // OHA, what the approver granted.

            // What payroll is owed for this day. Written only alongside an
            // approval; KOL-46 owns the full derivation and its legal-cap edge.
            $table->time('final_hours')->nullable();

            $table->string('status')->default(OvertimeAuthorizationStatus::Pending->value);

            // Who decided and when, following the reviewer columns of
            // `mark_modifications` rather than inventing a second shape. The
            // reason is optional in general and mandatory over a legal cap
            // (KOL-41) or without a covering pacto (KOL-42).
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reason')->nullable();

            // The pacto de horas extraordinarias covering the worked date, when
            // one exists. Deliberately unconstrained for now: KOL-42 creates the
            // table and adds the foreign key. A missing pacto is never a bar to
            // approval (see backlog/decisions/decision-1), so this stays
            // nullable afterwards too.
            $table->unsignedBigInteger('overtime_pact_id')->nullable();

            $table->timestamps();

            // The supervisors' queue (PRD §7.5) and the export (§7.7) both read
            // a tenant's rows filtered by status.
            $table->index(['organization_id', 'status']);
            // The payroll period selection: one tenant, one date range.
            $table->index(['organization_id', 'date']);
            // One employee's period, for the per-employee summaries.
            $table->index(['user_id', 'date']);
        });
    }
};
