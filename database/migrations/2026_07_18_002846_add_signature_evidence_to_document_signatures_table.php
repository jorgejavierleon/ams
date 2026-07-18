<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the firma electrónica simple (FES) evidence a signature must carry to
     * be legally defensible under Ley 19.799: the one-time verification code
     * that authors the signing act, and — captured at the moment of signing —
     * the signer's IP, user agent, a SHA-256 hash of the exact content they
     * consented to, and, for a rejection, the stated reason.
     */
    public function up(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->string('verification_code')->nullable()->after('order');
            $table->timestamp('verification_code_expires_at')->nullable()->after('verification_code');
            $table->string('signed_ip', 45)->nullable()->after('signed_at');
            $table->text('signed_user_agent')->nullable()->after('signed_ip');
            $table->string('signed_content_hash')->nullable()->after('signed_user_agent');
            $table->string('rejection_reason')->nullable()->after('signed_content_hash');
        });
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropColumn([
                'verification_code',
                'verification_code_expires_at',
                'signed_ip',
                'signed_user_agent',
                'signed_content_hash',
                'rejection_reason',
            ]);
        });
    }
};
