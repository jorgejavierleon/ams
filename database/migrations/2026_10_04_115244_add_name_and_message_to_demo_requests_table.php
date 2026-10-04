<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the name/message fields collected by the redesigned demo-request
     * form (KOL-142.3).
     */
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->text('message')->nullable()->after('email');
        });
    }
};
