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
            $table->foreignId('supervisor_id')
                ->nullable()
                ->after('premise_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_admin')->default(false)->after('is_legal_rep');
            $table->date('contract_start_date')->nullable()->after('rut');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
            $table->float('vacation_days')->default(0)->after('contract_end_date');
            $table->float('additional_vacation_days')->default(0)->after('vacation_days');
            $table->float('administrative_days')->default(0)->after('additional_vacation_days');
            $table->boolean('has_additional_sundays')->default(false)->after('administrative_days');
            $table->string('nationality')->nullable()->after('has_additional_sundays');
            $table->string('gender')->nullable()->after('nationality');
            $table->string('phone')->nullable()->after('gender');
            $table->string('emergency_contact_name')->nullable()->after('phone');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('timezone')->default('America/Santiago')->after('emergency_contact_phone');
        });
    }
};
