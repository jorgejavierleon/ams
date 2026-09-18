<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Presents Spatie role and permission names as human-readable, localized labels
 * for the roles-management screens.
 *
 * The internal Spatie names stay authoritative — application code, policies and
 * the `permission:` middleware keep gating on the raw `employee` /
 * `ViewOwn:Mark` identifiers — while these helpers resolve only the Spanish (or
 * English) wording shown to admins. A missing translation degrades to a
 * title-cased version of the raw name rather than the bare identifier.
 */
final class RolePresenter
{
    /** Roles reserved for system use — admins cannot manage these. */
    public const PROTECTED_ROLES = ['admin', 'dt', 'saas'];

    /**
     * The role every employee record must keep. {@see User::scopeEmployees()}
     * and `EmployeeController::assertEmployee()` both hard-require it to
     * recognize a User as an employee at all, so — unlike the roles in
     * PROTECTED_ROLES — it is not merely unmanaged, it must never be removable.
     */
    public const BASE_EMPLOYEE_ROLE = 'employee';

    /**
     * Scope a Role query down to roles admins are allowed to view or assign.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public static function excludeProtected(Builder $query): Builder
    {
        return $query->whereNotIn('name', self::PROTECTED_ROLES);
    }

    /**
     * Sync a role selection onto a user without ever adding or dropping a
     * protected role (or any other name in `$alwaysPreserve`) — whatever the
     * user already holds among those survives untouched, and a submitted id
     * for one is never honored. Used by any screen that lets an admin sync a
     * user's roles from a curated (non-protected) checklist.
     *
     * @param  array<int, int>  $roleIds
     * @param  array<int, string>  $alwaysPreserve  Extra role names (besides
     *                                              PROTECTED_ROLES) that must also survive untouched, e.g.
     *                                              BASE_EMPLOYEE_ROLE on the employee edit form.
     */
    public static function syncAssignableRoles(User $user, array $roleIds, array $alwaysPreserve = []): void
    {
        $preservedNames = [...self::PROTECTED_ROLES, ...$alwaysPreserve];

        $preservedIds = $user->roles()->whereIn('name', $preservedNames)->pluck('id');
        $assignableIds = Role::whereKey($roleIds)->whereNotIn('name', $preservedNames)->pluck('id');

        $user->syncRoles($assignableIds->merge($preservedIds));
    }

    /**
     * Localized display name for a role (e.g. `employee` → "Empleado").
     */
    public static function roleLabel(string $role): string
    {
        return self::translate("ui.roles.names.{$role}", Str::headline($role));
    }

    /**
     * Localized label for a permission (e.g. `ViewOwn:Mark` → "Ver marcas propias").
     */
    public static function permissionLabel(string $permission): string
    {
        return self::translate("ui.roles.permissions.{$permission}", Str::headline($permission));
    }

    /**
     * The resource a permission is grouped under, taken from the part after the
     * colon (`ViewOwn:Mark` → "Mark") or, for colon-less names, the segment
     * after the last underscore (`view_employee` → "employee"). Used as the
     * stable grouping key on the role detail screen.
     */
    public static function groupKey(string $permission): string
    {
        if (Str::contains($permission, ':')) {
            return Str::afterLast($permission, ':');
        }

        return Str::contains($permission, '_')
            ? Str::afterLast($permission, '_')
            : $permission;
    }

    /**
     * Localized label for a permission group resource (`Mark` → "Asistencia").
     */
    public static function groupLabel(string $groupKey): string
    {
        return self::translate("ui.roles.groups.{$groupKey}", Str::headline($groupKey));
    }

    /**
     * Resolve a translation key, falling back to the given default when the key
     * has no entry in the active (or fallback) locale.
     */
    private static function translate(string $key, string $fallback): string
    {
        return Lang::has($key) ? (string) __($key) : $fallback;
    }
}
