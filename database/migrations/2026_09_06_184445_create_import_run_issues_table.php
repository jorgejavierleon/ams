<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One ImportIssue (KOL-94.3) persisted per row/field so the preview
     * step's per-row table (KOL-111) can query/paginate what
     * PreviewImportRun's ephemeral ImportRow objects would otherwise
     * discard — the same detail ImportErrorReportWriter (KOL-103) already
     * writes to the post-commit CSV.
     */
    public function up(): void
    {
        Schema::create('import_run_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('field')->nullable();
            $table->string('severity');
            $table->text('message');
            $table->timestamps();

            $table->index(['import_run_id', 'row_number']);
        });
    }
};
