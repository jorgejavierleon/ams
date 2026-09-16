import { Form, Head } from '@inertiajs/react';
import type { LayoutCallback } from '@inertiajs/react';
import UserRoleController from '@/actions/App/Http/Controllers/UserRoleController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { translate } from '@/lib/i18n';
import { index as rolesIndex } from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    label: string;
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
    const { t } = useTranslations();

    return (
        <>
            <Head title={user.name} />

            <div className="space-y-6 p-6">
                <Heading
                    title={user.name}
                    description={t('ui.user_roles.description', {
                        email: user.email,
                    })}
                />

                <Form
                    {...UserRoleController.update.form(user)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <div className="space-y-6">
                            <div className="space-y-3">
                                <h3 className="text-sm font-semibold text-foreground">
                                    {t('ui.roles.title')}
                                </h3>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                    {roles.map((role) => (
                                        <div
                                            key={role.id}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={`role-${role.id}`}
                                                name="roles[]"
                                                value={role.id}
                                                defaultChecked={role.assigned}
                                            />
                                            <Label
                                                htmlFor={`role-${role.id}`}
                                                className="cursor-pointer text-sm font-normal"
                                            >
                                                {role.label}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? t('ui.user_roles.saving')
                                    : t('ui.user_roles.save')}
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

const layout: LayoutCallback = (props) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'ui.roles.title'),
            href: rolesIndex(),
        },
        {
            title: translate(props.translations, 'ui.user_roles.breadcrumb'),
            href: '#',
        },
    ],
});

UserRoles.layout = layout;
