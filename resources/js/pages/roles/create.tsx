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
import { store } from '@/routes/roles';

type Props = {
    permissionGroups: RolePermissionGroup[];
};

export default function RolesCreate({ permissionGroups }: Props) {
    const { t } = useTranslations();

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        permissions: number[];
    }>({
        name: '',
        permissions: [],
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
        post(store().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title={t('ui.roles.create_page.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.roles.create_page.title')}
                    description={t('ui.roles.create_page.description')}
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2 sm:max-w-sm">
                        <Label htmlFor="name">{t('ui.roles.form.name')}</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder={t('ui.roles.form.name_placeholder')}
                            required
                            autoFocus
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
                            : t('ui.roles.create_page.submit')}
                    </Button>
                </form>
            </div>
        </>
    );
}
