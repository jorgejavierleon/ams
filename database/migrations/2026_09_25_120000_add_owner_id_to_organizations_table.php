<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KOL-133: replace the all-or-nothing `admin` role bypass with a real Owner
 * per organization — one user who unconditionally bypasses every
 * authorization check in their organization, independent of any role or
 * permission they hold. That lets the `admin` Spatie role become a normal,
 * editable role in a later migration (KOL-133.3) without risking an
 * organization locking itself out of its own admin permissions.
 *
 * Backfill: for each organization, the earliest-created user (by
 * `users.created_at`) currently holding the `admin` role becomes the owner.
 * Organizations with no admin user today are left without an owner rather
 * than guessing — this is not resolvable from existing data. Queried via
 * `model_has_roles`/`roles`/`users` with the query builder, not Eloquent
 * models or scopes, since this is data cleanup rather than application
 * behaviour (User::class is only referenced for its string value, to match
 * the polymorphic `model_type` column).
 *
 * Irreversible by design: `down()` cannot know which owner_id values were
 * backfilled versus set afterwards, so undoing this is not a decision a
 * rollback should make silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('plan')
                ->constrained('users')
                ->restrictOnDelete();
        });

        $earliestAdminPerOrganization = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('roles.name', 'admin')
            ->where('model_has_roles.model_type', User::class)
            ->whereNotNull('users.organization_id')
            ->orderBy('users.created_at')
            ->select('users.id as user_id', 'users.organization_id')
            ->get()
            ->unique('organization_id');

        foreach ($earliestAdminPerOrganization as $row) {
            DB::table('organizations')
                ->where('id', $row->organization_id)
                ->update(['owner_id' => $row->user_id]);
        }
    }
};
