<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `overtime_pacts` did not exist when `overtime_authorizations` was
     * created, so `overtime_pact_id` was left unconstrained (KOL-42). It stays
     * nullable: a missing pacto is a flag, never a bar to approval (see
     * backlog/decisions/decision-1).
     */
    public function up(): void
    {
        Schema::table('overtime_authorizations', function (Blueprint $table) {
            $table->foreign('overtime_pact_id')
                ->references('id')->on('overtime_pacts')
                ->nullOnDelete();
        });
    }
};
