<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The *centro de costo* the payroll reports segment by — an accounting bucket
 * owned by the tenant, in the same shape as `positions`.
 *
 * This is deliberately not the employer: `companies` holds the legal entity a
 * DT inspector audits, and a tenant has exactly one. A cost centre has no RUT
 * and must never stand in for the employer on a Resolución 38 report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
