<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual kill-switch (KOL-137.4), independent of the KOL-137.3 automatic
     * soft/hard-limit enforcement. Null means "no manual override": the
     * automatic enforcement decides. true/false unconditionally win over
     * that automatic decision in either direction.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('email_sending_override')->nullable()->after('hard_email_limit_override');
        });
    }
};
