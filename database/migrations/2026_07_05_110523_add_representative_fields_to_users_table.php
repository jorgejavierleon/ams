<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('organization_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('second_last_name')->nullable()->after('last_name');
            $table->string('rut')->nullable()->unique()->after('second_last_name');
            $table->string('personal_email')->nullable()->after('email');
            $table->boolean('is_legal_rep')->default(false)->after('is_active');
        });
    }
};
