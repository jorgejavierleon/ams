<?php

use App\Enums\OvertimeAuthorizationMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New organizations now default to Combined (pre-authorization and
     * post-hoc review both active) rather than post-hoc only. Existing rows
     * are left untouched — this only changes the column default for rows
     * created from here on.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('overtime_authorization_mode')
                ->default(OvertimeAuthorizationMode::Combined->value)
                ->change();
        });
    }
};
