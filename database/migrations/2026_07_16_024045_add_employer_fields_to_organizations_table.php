<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give the organization the employer identity a DT inspector audits: its
     * RUT (razón social is the existing `name`) plus contact details. Nullable
     * so existing rows survive; the audit notification only fires when an email
     * is present.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('rut')->nullable()->unique()->after('name');
            $table->string('email')->nullable()->after('rut');
            $table->string('phone')->nullable()->after('email');
            $table->string('address')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['rut']);
            $table->dropColumn(['rut', 'email', 'phone', 'address']);
        });
    }
};
