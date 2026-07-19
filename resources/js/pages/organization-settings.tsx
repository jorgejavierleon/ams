import { Head, useForm } from '@inertiajs/react';
import { Bell, FileSignature } from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/hooks/use-translations';
import { edit, update } from '@/routes/organization-settings';

type SettingsForm = {
    employee_missing_in_notification: boolean;
    employee_missing_out_notification: boolean;
    employer_missing_in_notification: boolean;
    employer_missing_out_notification: boolean;
    leave_approval_notification: boolean;
    documents_signature_enabled: boolean;
    documents_require_ordered_signing: boolean;
};

type Props = {
    settings: SettingsForm;
};

export default function OrganizationSettings({ settings }: Props) {
    const { t } = useTranslations();
    const { data, setData, patch, processing } = useForm<SettingsForm>({
        ...settings,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(update().url, { preserveScroll: true });
    }

    const notificationKeys: Array<keyof SettingsForm> = [
        'employee_missing_in_notification',
        'employee_missing_out_notification',
        'employer_missing_in_notification',
        'employer_missing_out_notification',
        'leave_approval_notification',
    ];

    const documentKeys: Array<keyof SettingsForm> = [
        'documents_signature_enabled',
        'documents_require_ordered_signing',
    ];

    return (
        <>
            <Head title={t('ui.organization_settings.title')} />

            <form onSubmit={submit} className="max-w-3xl space-y-6 p-6">
                <Heading
                    title={t('ui.organization_settings.title')}
                    description={t('ui.organization_settings.description')}
                />

                <Section
                    title={t('ui.organization_settings.sections.notifications')}
                    icon={<Bell className="size-4" />}
                >
                    {notificationKeys.map((key) => (
                        <SettingToggle
                            key={key}
                            id={key}
                            label={t(
                                `ui.organization_settings.fields.${key}.label`,
                            )}
                            hint={t(
                                `ui.organization_settings.fields.${key}.hint`,
                            )}
                            checked={data[key]}
                            onCheckedChange={(value) => setData(key, value)}
                        />
                    ))}
                </Section>

                <Section
                    title={t('ui.organization_settings.sections.documents')}
                    icon={<FileSignature className="size-4" />}
                >
                    {documentKeys.map((key) => (
                        <SettingToggle
                            key={key}
                            id={key}
                            label={t(
                                `ui.organization_settings.fields.${key}.label`,
                            )}
                            hint={t(
                                `ui.organization_settings.fields.${key}.hint`,
                            )}
                            checked={data[key]}
                            onCheckedChange={(value) => setData(key, value)}
                        />
                    ))}
                </Section>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        {t('ui.common.save')}
                    </Button>
                </div>
            </form>
        </>
    );
}

/** A titled section card grouping related settings. */
function Section({
    title,
    icon,
    children,
}: {
    title: string;
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-xl border bg-card shadow-xs">
            <div className="flex items-center gap-2 border-b px-5 py-3.5">
                <h2 className="flex items-center gap-2 text-[13px] font-semibold">
                    {icon}
                    {title}
                </h2>
            </div>
            <div className="divide-y">{children}</div>
        </section>
    );
}

/** A single labelled toggle row. */
function SettingToggle({
    id,
    label,
    hint,
    checked,
    onCheckedChange,
}: {
    id: string;
    label: string;
    hint: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-6 px-5 py-4">
            <div className="space-y-0.5">
                <Label
                    htmlFor={id}
                    className="cursor-pointer text-sm font-medium"
                >
                    {label}
                </Label>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>
            <Switch
                id={id}
                checked={checked}
                onCheckedChange={onCheckedChange}
            />
        </div>
    );
}

OrganizationSettings.layout = {
    breadcrumbs: [{ title: 'Organization settings', href: edit() }],
};
