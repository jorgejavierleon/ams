import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { RolePermissionGroups } from '@/components/role-permission-groups';
import type { RolePermissionGroup } from '@/components/role-permission-groups';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    label: string;
};

type Props = {
    role: Role;
    permissionGroups: RolePermissionGroup[];
};

export default function RolesShow({ role, permissionGroups }: Props) {
    const { t } = useTranslations();

    const { data, setData, put, processing, errors } = useForm<{
        name: string;
        permissions: number[];
    }>({
        name: role.name,
        permissions: permissionGroups
            .flatMap((g) => g.permissions)
            .filter((p) => p.assigned)
            .map((p) => p.id),
    });

    const selectedIds = new Set(data.permissions);

    function togglePermission(id: number, checked: boolean) {
        setData(
            'permissions',
            checked
                ? [...data.permissions, id]
                : data.permissions.filter(
                      (permissionId) => permissionId !== id,
                  ),
        );
    }

    function toggleGroup(group: RolePermissionGroup, checked: boolean) {
        const next = new Set(data.permissions);

        for (const permission of group.permissions) {
            if (checked) {
                next.add(permission.id);
            } else {
                next.delete(permission.id);
            }
        }

        setData('permissions', Array.from(next));
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        put(update(role.id).url, { preserveScroll: true });
    }

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
                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2 sm:max-w-sm">
                            <Label htmlFor="name">
                                {t('ui.roles.form.name')}
                            </Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <RolePermissionGroups
                            permissionGroups={permissionGroups}
                            selectedIds={selectedIds}
                            onTogglePermission={togglePermission}
                            onToggleGroup={toggleGroup}
                        />

                        <Button type="submit" disabled={processing}>
                            {processing
                                ? t('ui.roles.saving')
                                : t('ui.roles.save')}
                        </Button>
                    </form>
                )}
            </div>
        </>
    );
}
