<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An employee charges to at most one cost centre. Nullable because the
 * dimension is optional: a tenant that never sets up a cost-centre catalogue
 * keeps every employee working with none assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cost_center_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();
        });
    }
};
