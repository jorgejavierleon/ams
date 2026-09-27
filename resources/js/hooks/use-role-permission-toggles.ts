import type { RolePermissionGroup } from '@/components/role-permission-groups';

/**
 * Toggle handlers for a role's permission checklist, shared by the create
 * and edit screens. Owns no state itself — it reads the current selection
 * and writes back through `setPermissions`, so callers can wire it straight
 * to `useForm`'s `setData('permissions', ...)`.
 */
export function useRolePermissionToggles(
    permissions: number[],
    setPermissions: (value: number[]) => void,
) {
    function togglePermission(id: number, checked: boolean) {
        setPermissions(
            checked
                ? [...permissions, id]
                : permissions.filter((permissionId) => permissionId !== id),
        );
    }

    function toggleGroup(group: RolePermissionGroup, checked: boolean) {
        const next = new Set(permissions);

        for (const permission of group.permissions) {
            if (checked) {
                next.add(permission.id);
            } else {
                next.delete(permission.id);
            }
        }

        setPermissions(Array.from(next));
    }

    return { togglePermission, toggleGroup };
}
