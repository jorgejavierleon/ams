<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The landing page's contact form captures leads, not just demo
     * requests — rename the table to match (KOL-142.3).
     */
    public function up(): void
    {
        Schema::rename('demo_requests', 'leads');
    }
};
