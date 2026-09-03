<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KOL-98: the upload step needs somewhere to persist the uploaded file
     * for later wizard steps (mapping/preview/commit) to re-read, and the
     * original filename for display — neither existed on the KOL-96/97
     * migration since that ticket predates the upload step.
     */
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->string('disk_path')->nullable()->after('match_key');
            $table->string('original_filename')->nullable()->after('disk_path');
        });
    }
};
