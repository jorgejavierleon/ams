import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/hooks/use-translations';

export type RolePermission = {
    id: number;
    name: string;
    label: string;
    assigned: boolean;
};

export type RolePermissionGroup = {
    group: string;
    permissions: RolePermission[];
};

type Props = {
    permissionGroups: RolePermissionGroup[];
    selectedIds: Set<number>;
    onTogglePermission: (id: number, checked: boolean) => void;
    onToggleGroup: (group: RolePermissionGroup, checked: boolean) => void;
};

/**
 * The bordered white-card permission checklist shared by the role create and
 * edit screens. Selection state lives with the caller (create starts empty,
 * edit starts from the role's current permissions).
 */
export function RolePermissionGroups({
    permissionGroups,
    selectedIds,
    onTogglePermission,
    onToggleGroup,
}: Props) {
    const { t } = useTranslations();

    return (
        <div className="space-y-6">
            {permissionGroups.map((group) => {
                const groupIds = group.permissions.map((p) => p.id);
                const selectedCount = groupIds.filter((id) =>
                    selectedIds.has(id),
                ).length;
                const allSelected = selectedCount === groupIds.length;
                const groupCheckboxState: boolean | 'indeterminate' =
                    allSelected
                        ? true
                        : selectedCount > 0
                          ? 'indeterminate'
                          : false;

                return (
                    <div
                        key={group.group}
                        className="rounded-lg border bg-card p-4 shadow-sm"
                    >
                        <div className="flex items-center justify-between gap-4">
                            <h3 className="text-sm font-semibold text-foreground">
                                {group.group}
                            </h3>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id={`group-${group.group}`}
                                    checked={groupCheckboxState}
                                    onCheckedChange={(checked) =>
                                        onToggleGroup(group, checked === true)
                                    }
                                />
                                <Label
                                    htmlFor={`group-${group.group}`}
                                    className="cursor-pointer text-xs font-normal text-muted-foreground"
                                >
                                    {t('ui.roles.select_all')}
                                </Label>
                            </div>
                        </div>

                        <Separator className="my-3" />

                        <div className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                            {group.permissions.map((permission) => (
                                <div
                                    key={permission.id}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`permission-${permission.id}`}
                                        checked={selectedIds.has(permission.id)}
                                        onCheckedChange={(checked) =>
                                            onTogglePermission(
                                                permission.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`permission-${permission.id}`}
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        {permission.label}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
