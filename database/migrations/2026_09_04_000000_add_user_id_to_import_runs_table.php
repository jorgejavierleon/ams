<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KOL-105: import_runs only carried organization_id, so any user holding
     * Import:Employee in the org could view/edit another user's in-progress
     * ImportRun by editing the id in the URL. Adds the `user_id` "requester"
     * column ReportExport already carries for the same reason.
     */
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->foreignId('user_id')->after('organization_id')->constrained()->cascadeOnDelete();
        });
    }
};
