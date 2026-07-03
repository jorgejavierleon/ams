import { Form, Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/roles';

type Permission = {
    id: number;
    name: string;
    assigned: boolean;
};

type PermissionGroup = {
    group: string;
    permissions: Permission[];
};

type Role = {
    id: number;
    name: string;
};

type Props = {
    role: Role;
    permissionGroups: PermissionGroup[];
};

export default function RolesShow({ role, permissionGroups }: Props) {
    const initialPermissions = permissionGroups
        .flatMap((g) => g.permissions)
        .filter((p) => p.assigned)
        .map((p) => p.id);

    return (
        <>
            <Head title={`${role.name} — Permissions`} />

            <div className="space-y-6 p-6">
                <Heading
                    title={<span className="capitalize">{role.name}</span>}
                    description="Toggle permissions on or off for this role"
                />

                {permissionGroups.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No permissions defined yet. Add permissions to the system to manage them here.
                    </p>
                ) : (
                    <Form
                        {...RoleController.update.form(role)}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <div className="space-y-8">
                                {permissionGroups.map((group) => (
                                    <div key={group.group} className="space-y-3">
                                        <h3 className="text-sm font-semibold text-foreground">{group.group}</h3>
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                            {group.permissions.map((permission) => (
                                                <div key={permission.id} className="flex items-center gap-2">
                                                    <Checkbox
                                                        id={`permission-${permission.id}`}
                                                        name="permissions[]"
                                                        value={permission.id}
                                                        defaultChecked={initialPermissions.includes(permission.id)}
                                                    />
                                                    <Label
                                                        htmlFor={`permission-${permission.id}`}
                                                        className="cursor-pointer text-sm font-normal"
                                                    >
                                                        {permission.name}
                                                    </Label>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}

                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : 'Save permissions'}
                                </Button>
                            </div>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

RolesShow.layout = {
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: 'Permissions', href: '#' },
    ],
};
