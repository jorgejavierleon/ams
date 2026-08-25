<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A report export that ran (or is running) as a queued job rather than a
     * synchronous download (KOL-16): the filters needed to rebuild it, where
     * the finished file lives on the private disk, and when the signed
     * download link expires.
     */
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('format');
            $table->json('filters');
            $table->string('status');
            $table->string('disk_path')->nullable();
            $table->string('filename')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }
};
