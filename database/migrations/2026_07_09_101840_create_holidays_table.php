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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            // NULL = official/global holiday (synced from Boostr, read-only to
            // organizations). Non-null = a holiday owned by a single organization.
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('country')->default('cl');
            $table->string('name');
            $table->date('date');
            $table->boolean('mandatory')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'country', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
