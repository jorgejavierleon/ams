<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

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
