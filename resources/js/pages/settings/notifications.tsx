import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { SettingToggle } from '@/components/settings/setting-toggle';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/settings-notifications';

type NotificationsForm = {
    employee_missing_in_notification: boolean;
    employee_missing_out_notification: boolean;
    employer_missing_in_notification: boolean;
    employer_missing_out_notification: boolean;
    leave_approval_notification: boolean;
};

const fieldKeys = Object.keys({
    employee_missing_in_notification: true,
    employee_missing_out_notification: true,
    employer_missing_in_notification: true,
    employer_missing_out_notification: true,
    leave_approval_notification: true,
} satisfies NotificationsForm) as (keyof NotificationsForm)[];

type Props = {
    settings: NotificationsForm;
};

export default function Notifications({ settings }: Props) {
    const { t } = useTranslations();
    const { data, setData, patch, processing } = useForm<NotificationsForm>({
        ...settings,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(update().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title={t('ui.settings.notifications.head')} />

            <h1 className="sr-only">{t('ui.settings.notifications.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('ui.settings.notifications.title')}
                    description={t('ui.settings.notifications.description')}
                />

                <Card>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="divide-y">
                                {fieldKeys.map((key) => (
                                    <SettingToggle
                                        key={key}
                                        id={key}
                                        label={t(
                                            `ui.settings.notifications.fields.${key}.label`,
                                        )}
                                        hint={t(
                                            `ui.settings.notifications.fields.${key}.hint`,
                                        )}
                                        checked={data[key]}
                                        onCheckedChange={(value) =>
                                            setData(key, value)
                                        }
                                    />
                                ))}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {t('ui.common.save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
