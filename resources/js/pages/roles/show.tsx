import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { RolePermissionGroups } from '@/components/role-permission-groups';
import type { RolePermissionGroup } from '@/components/role-permission-groups';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useRolePermissionToggles } from '@/hooks/use-role-permission-toggles';
import { useTranslations } from '@/hooks/use-translations';
import { restoreDefaults, update } from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    label: string;
    is_system_role: boolean;
};

type Props = {
    role: Role;
    permissionGroups: RolePermissionGroup[];
};

export default function RolesShow({ role, permissionGroups }: Props) {
    const { t } = useTranslations();
    const [restoreOpen, setRestoreOpen] = useState(false);
    const [restoring, setRestoring] = useState(false);

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
    const { togglePermission, toggleGroup } = useRolePermissionToggles(
        data.permissions,
        (value) => setData('permissions', value),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        put(update(role.id).url, { preserveScroll: true });
    }

    function confirmRestore() {
        router.post(
            restoreDefaults(role.id).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setRestoring(true),
                onFinish: () => {
                    setRestoring(false);
                    setRestoreOpen(false);
                },
            },
        );
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
                                disabled={role.is_system_role}
                                readOnly={role.is_system_role}
                            />
                            <InputError message={errors.name} />
                            {role.is_system_role && (
                                <p className="text-sm text-muted-foreground">
                                    {t('ui.roles.system_role_hint')}
                                </p>
                            )}
                        </div>

                        <RolePermissionGroups
                            permissionGroups={permissionGroups}
                            selectedIds={selectedIds}
                            onTogglePermission={togglePermission}
                            onToggleGroup={toggleGroup}
                        />

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? t('ui.roles.saving')
                                    : t('ui.roles.save')}
                            </Button>

                            {role.is_system_role && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setRestoreOpen(true)}
                                >
                                    {t('ui.roles.restore_defaults')}
                                </Button>
                            )}
                        </div>
                    </form>
                )}
            </div>

            <ConfirmDialog
                open={restoreOpen}
                onOpenChange={setRestoreOpen}
                title={t('ui.roles.restore_defaults_dialog.title')}
                description={t('ui.roles.restore_defaults_dialog.description', {
                    name: role.label,
                })}
                confirmLabel={t('ui.roles.restore_defaults_dialog.confirm')}
                onConfirm={confirmRestore}
                processing={restoring}
            />
        </>
    );
}
