import { Form, Head, type LayoutCallback } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { translate } from '@/lib/i18n';
import { index } from '@/routes/roles';

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

    const initialPermissions = permissionGroups
        .flatMap((g) => g.permissions)
        .filter((p) => p.assigned)
        .map((p) => p.id);

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
                        {...RoleController.update.form({ id: String(role.id) })}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <div className="space-y-8">
                                {permissionGroups.map((group) => (
                                    <div
                                        key={group.group}
                                        className="space-y-3"
                                    >
                                        <h3 className="text-sm font-semibold text-foreground">
                                            {group.group}
                                        </h3>
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                            {group.permissions.map(
                                                (permission) => (
                                                    <div
                                                        key={permission.id}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            id={`permission-${permission.id}`}
                                                            name="permissions[]"
                                                            value={
                                                                permission.id
                                                            }
                                                            defaultChecked={initialPermissions.includes(
                                                                permission.id,
                                                            )}
                                                        />
                                                        <Label
                                                            htmlFor={`permission-${permission.id}`}
                                                            className="cursor-pointer text-sm font-normal"
                                                        >
                                                            {permission.label}
                                                        </Label>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                ))}

                                <Button type="submit" disabled={processing}>
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

const layout: LayoutCallback = (props) => ({
    breadcrumbs: [
        { title: 'Roles', href: index() },
        {
            title: translate(props.translations, 'ui.roles.columns.permissions'),
            href: '#',
        },
    ],
});

RolesShow.layout = layout;
