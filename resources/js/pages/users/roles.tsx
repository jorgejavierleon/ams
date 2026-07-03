import { Form, Head } from '@inertiajs/react';
import UserRoleController from '@/actions/App/Http/Controllers/UserRoleController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as rolesIndex } from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    assigned: boolean;
};

type UserInfo = {
    id: number;
    name: string;
    email: string;
};

type Props = {
    user: UserInfo;
    roles: Role[];
};

export default function UserRoles({ user, roles }: Props) {
    return (
        <>
            <Head title={`${user.name} — Roles`} />

            <div className="space-y-6 p-6">
                <Heading
                    title={user.name}
                    description={`Assign or remove roles for ${user.email}`}
                />

                <Form
                    {...UserRoleController.update.form(user)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <div className="space-y-6">
                            <div className="space-y-3">
                                <h3 className="text-sm font-semibold text-foreground">Roles</h3>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                    {roles.map((role) => (
                                        <div key={role.id} className="flex items-center gap-2">
                                            <Checkbox
                                                id={`role-${role.id}`}
                                                name="roles[]"
                                                value={role.id}
                                                defaultChecked={role.assigned}
                                            />
                                            <Label
                                                htmlFor={`role-${role.id}`}
                                                className="cursor-pointer text-sm font-normal capitalize"
                                            >
                                                {role.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving…' : 'Save roles'}
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

UserRoles.layout = {
    breadcrumbs: [
        { title: 'Roles', href: rolesIndex() },
        { title: 'User Roles', href: '#' },
    ],
};
