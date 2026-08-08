<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A queued punch older than the offline cap is not inserted as a mark; it is
     * filed as a pending addition (Resolución 38 Art. 39 b). Approving one
     * creates the mark, so the request has to carry the provenance forward —
     * otherwise the mark that eventually lands looks like an ordinary punch and
     * the register can no longer say which marks were queued (Art. 10).
     */
    public function up(): void
    {
        Schema::table('mark_modifications', function (Blueprint $table) {
            $table->dateTime('device_datetime')->nullable()->after('original_date_time');
            $table->boolean('captured_offline')->default(false)->after('device_datetime');
        });
    }
};
