<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provenance for a punch captured while the device had no connection
     * (Resolución 38 Art. 10). The legal timestamp stays in `date_time`; these
     * columns record where it came from and how it got here.
     */
    public function up(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            // The raw phone reading, exactly as it was sent, kept beside the
            // adjudicated `date_time` permanently so the two can always be
            // compared. Null on an online punch, which never sends one.
            $table->dateTime('device_datetime')->nullable()->after('date_time');
            // When the register received the punch. Equal to `date_time` on an
            // online punch; on a queued one it is the transmission time, and the
            // gap between the two is the queue's own age.
            $table->dateTime('synced_at')->nullable()->after('device_datetime');
            // Art. 10 second paragraph limits the offline exception to
            // `situaciones excepcionales`, which cannot be justified unless the
            // register can say which marks were queued — so this is a stored
            // fact, never inferred from the gap between the two datetimes above.
            $table->boolean('captured_offline')->default(false)->after('synced_at');
            // Generated on the device when the punch is queued and never
            // regenerated on a retry: a retry whose answer was lost is not a
            // second punch.
            $table->uuid('idempotency_key')->nullable()->after('captured_offline');

            // What makes the guarantee real rather than a best effort. Scoped
            // per employee, never global: two devices can only collide on a key
            // by accident, and one employee's accident must not refuse
            // another's punch. MySQL admits any number of NULLs here, so every
            // online punch is unaffected.
            $table->unique(['user_id', 'idempotency_key']);
            // Offline frequency is reported per employee and per premise
            // (Art. 10), which reads this flag over a date range.
            $table->index(['organization_id', 'captured_offline', 'date_time']);
        });
    }
};
