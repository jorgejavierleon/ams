<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per outgoing email actually sent (KOL-137.1), attributed to an
     * organization via the sending class's own organization_id metadata
     * rather than the recipient address. Deliberately not scoped by
     * BelongsToOrganization: the SaaS panel has no tenant context and must
     * see every organization's volume.
     */
    public function up(): void
    {
        Schema::create('email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }
};
