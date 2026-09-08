<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KOL-107: the wizard supported only Employee, so no column ever
     * recorded which resource a run belonged to. A queued ProcessImportRun
     * job has no route/request context to resolve the ImportSchema from, so
     * the run has to carry its own resource-type key for the job (and any
     * later {resourceType}/{importRun} request) to resolve against
     * ImportResourceRegistry. Defaults to 'employees' so this stays a plain
     * ADD COLUMN against any environment with existing rows — every run
     * that already exists was necessarily an Employee import, since that
     * was the only resource type the wizard supported until now.
     */
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->string('resource_type')->default('employees')->after('organization_id');
        });
    }
};
