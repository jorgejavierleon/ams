import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/hooks/use-translations';

type Permission = {
    id: number;
    name: string;
    label: string;
    assigned: boolean;
};

type PermissionGroup = {
    group: string;
    permissions: Permission[];
};

type Role = {
    id: number;
    name: string;
    label: string;
};

type Props = {
    role: Role;
    permissionGroups: PermissionGroup[];
};

export default function RolesShow({ role, permissionGroups }: Props) {
    const { t } = useTranslations();

    const [selectedIds, setSelectedIds] = useState<Set<number>>(
        () =>
            new Set(
                permissionGroups
                    .flatMap((g) => g.permissions)
                    .filter((p) => p.assigned)
                    .map((p) => p.id),
            ),
    );

    const togglePermission = (id: number, checked: boolean) => {
        setSelectedIds((previous) => {
            const next = new Set(previous);

            if (checked) {
                next.add(id);
            } else {
                next.delete(id);
            }

            return next;
        });
    };

    const toggleGroup = (group: PermissionGroup, checked: boolean) => {
        setSelectedIds((previous) => {
            const next = new Set(previous);

            for (const permission of group.permissions) {
                if (checked) {
                    next.add(permission.id);
                } else {
                    next.delete(permission.id);
                }
            }

            return next;
        });
    };

    return (
        <>
            <Head title={`${role.label} — Permissions`} />

            <div className="space-y-6 p-6">
                <Heading
                    title={role.label}
                    description={t('ui.roles.detail_description')}
                />

                {permissionGroups.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No permissions defined yet. Add permissions to the
                        system to manage them here.
                    </p>
                ) : (
                    <Form
                        {...RoleController.update.form({
                            id: String(role.id),
                        })}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <div className="space-y-6">
                                {permissionGroups.map((group) => {
                                    const groupIds = group.permissions.map(
                                        (p) => p.id,
                                    );
                                    const selectedCount = groupIds.filter(
                                        (id) => selectedIds.has(id),
                                    ).length;
                                    const allSelected =
                                        selectedCount === groupIds.length;
                                    const groupCheckboxState:
                                        | boolean
                                        | 'indeterminate' = allSelected
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
                                                        checked={
                                                            groupCheckboxState
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleGroup(
                                                                group,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor={`group-${group.group}`}
                                                        className="cursor-pointer text-xs font-normal text-muted-foreground"
                                                    >
                                                        {t(
                                                            'ui.roles.select_all',
                                                        )}
                                                    </Label>
                                                </div>
                                            </div>

                                            <Separator className="my-3" />

                                            <div className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                                                {group.permissions.map(
                                                    (permission) => (
                                                        <div
                                                            key={
                                                                permission.id
                                                            }
                                                            className="flex items-center gap-2"
                                                        >
                                                            <Checkbox
                                                                id={`permission-${permission.id}`}
                                                                name="permissions[]"
                                                                value={
                                                                    permission.id
                                                                }
                                                                checked={selectedIds.has(
                                                                    permission.id,
                                                                )}
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    togglePermission(
                                                                        permission.id,
                                                                        checked ===
                                                                            true,
                                                                    )
                                                                }
                                                            />
                                                            <Label
                                                                htmlFor={`permission-${permission.id}`}
                                                                className="cursor-pointer text-sm font-normal"
                                                            >
                                                                {
                                                                    permission.label
                                                                }
                                                            </Label>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}

                                <Button
                                    type="submit"
                                    disabled={processing}
                                >
                                    {processing
                                        ? t('ui.roles.saving')
                                        : t('ui.roles.save')}
                                </Button>
                            </div>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
