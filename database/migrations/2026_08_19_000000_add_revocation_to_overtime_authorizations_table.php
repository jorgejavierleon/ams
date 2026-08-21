<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KOL-80: an approved record can be revoked without being deleted. Kept
     * as columns distinct from `reviewed_by`/`reviewed_at`/`reason` — those
     * stay the approval's own audit trail, so a revoked row still answers
     * who approved it and who later revoked it as two separate facts.
     */
    public function up(): void
    {
        Schema::table('overtime_authorizations', function (Blueprint $table) {
            $table->foreignId('revoked_by')->nullable()->after('overtime_pact_id')->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('revoked_by');
            $table->text('revoked_reason')->nullable()->after('revoked_at');
        });
    }
};
