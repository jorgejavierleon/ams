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
        Schema::table('marks', function (Blueprint $table) {
            // The server's own geofence verdict at punch time — inside, outside
            // or unknown. Null on marks made before the endpoint evaluated one,
            // and on marks registered from the web app, which reports no
            // location at all.
            $table->string('geo_status')->nullable()->after('lng');
            // How uncertain the device said its fix was, in metres. It does not
            // change the verdict; it is what lets a reviewer weigh an `outside`
            // taken with a ±80 m fix against one taken with a ±5 m fix.
            $table->decimal('accuracy_meters', 8, 2)->nullable()->after('geo_status');
        });
    }
};
