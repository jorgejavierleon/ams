<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bulk data import (KOL-94): one uploaded file moving through mapping,
     * preview, and a queued commit. `preview_counts` holds the pre-commit
     * validation outcome (ready/warning/error/skipped); the four *_count
     * columns hold the actual commit-time upsert outcome — two distinct
     * measurements, per KOL-94.5.
     */
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->json('column_mapping')->nullable();
            $table->string('strategy')->nullable();
            $table->string('match_key')->nullable();
            $table->json('preview_counts')->nullable();
            $table->unsignedInteger('committed_through')->nullable();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('errored_count')->default(0);
            $table->string('error_report_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }
};
