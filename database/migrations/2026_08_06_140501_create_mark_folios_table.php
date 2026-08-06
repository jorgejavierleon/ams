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
        Schema::create('mark_folios', function (Blueprint $table) {
            $table->id();
            // Deliberately not a foreign key. This is a counter, not a
            // relation: it must keep its place in the sequence independently of
            // the rows it numbered, and it accepts 0 for the (unexpected) mark
            // that carries no organization, which a constrained column could not.
            $table->unsignedBigInteger('organization_id');
            $table->date('folio_date');
            // The last receipt number handed out to that organization on that
            // day. Allocation increments this row under a lock, so the unique
            // index below is what makes two simultaneous punches queue for the
            // next number instead of both reading the same one.
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'folio_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mark_folios');
    }
};
