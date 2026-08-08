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
        Schema::table('premises', function (Blueprint $table) {
            // How far from `lat`/`lng` still counts as being at the premise, in
            // whole metres. Null means the premise has no geofence at all: no
            // out-of-range state is ever shown and no punch is ever blocked.
            // A small integer caps the radius at 65 km, well past any premise.
            $table->unsignedSmallInteger('geofence_radius_meters')->nullable()->after('lng');
        });
    }
};
