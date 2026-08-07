import { Head, useForm } from '@inertiajs/react';
import { Bell, FileSignature, Timer } from 'lucide-react';
import type { ReactNode } from 'react';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/hooks/use-translations';
import { edit, update } from '@/routes/organization-settings';

type Option = { value: string; label: string };

type SettingsForm = {
    employee_missing_in_notification: boolean;
    employee_missing_out_notification: boolean;
    employer_missing_in_notification: boolean;
    employer_missing_out_notification: boolean;
    leave_approval_notification: boolean;
    documents_signature_enabled: boolean;
    documents_require_ordered_signing: boolean;
    overtime_authorization_mode: string;
    overtime_requires_pact: boolean;
    overtime_weekly_anomaly_threshold_hours: number;
    overtime_retroactive_request_days: number;
    overtime_default_compensation_type: string;
};

/** The keys whose control is a plain on/off switch. */
type ToggleKey = {
    [K in keyof SettingsForm]: SettingsForm[K] extends boolean ? K : never;
}[keyof SettingsForm];

type Props = {
    settings: SettingsForm;
    overtimeAuthorizationModeOptions: Option[];
    overtimeCompensationTypeOptions: Option[];
};

export default function OrganizationSettings({
    settings,
    overtimeAuthorizationModeOptions,
    overtimeCompensationTypeOptions,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, patch, processing, errors } = useForm<SettingsForm>({
        ...settings,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(update().url, { preserveScroll: true });
    }

    const notificationKeys: ToggleKey[] = [
        'employee_missing_in_notification',
        'employee_missing_out_notification',
        'employer_missing_in_notification',
        'employer_missing_out_notification',
        'leave_approval_notification',
    ];

    const documentKeys: ToggleKey[] = [
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

                <Section
                    title={t('ui.organization_settings.sections.overtime')}
                    icon={<Timer className="size-4" />}
                >
                    <div className="grid gap-6 px-5 py-5 sm:grid-cols-2">
                        <FormField
                            label={t(
                                'ui.organization_settings.fields.overtime_authorization_mode.label',
                            )}
                            htmlFor="overtime_authorization_mode"
                            hint={t(
                                'ui.organization_settings.fields.overtime_authorization_mode.hint',
                            )}
                            error={errors.overtime_authorization_mode}
                        >
                            <Select
                                value={data.overtime_authorization_mode}
                                onValueChange={(value) =>
                                    setData(
                                        'overtime_authorization_mode',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger id="overtime_authorization_mode">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {overtimeAuthorizationModeOptions.map(
                                        (option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            label={t(
                                'ui.organization_settings.fields.overtime_default_compensation_type.label',
                            )}
                            htmlFor="overtime_default_compensation_type"
                            hint={t(
                                'ui.organization_settings.fields.overtime_default_compensation_type.hint',
                            )}
                            error={errors.overtime_default_compensation_type}
                        >
                            <Select
                                value={data.overtime_default_compensation_type}
                                onValueChange={(value) =>
                                    setData(
                                        'overtime_default_compensation_type',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger id="overtime_default_compensation_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {overtimeCompensationTypeOptions.map(
                                        (option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            label={t(
                                'ui.organization_settings.fields.overtime_weekly_anomaly_threshold_hours.label',
                            )}
                            htmlFor="overtime_weekly_anomaly_threshold_hours"
                            hint={t(
                                'ui.organization_settings.fields.overtime_weekly_anomaly_threshold_hours.hint',
                            )}
                            error={
                                errors.overtime_weekly_anomaly_threshold_hours
                            }
                        >
                            <Input
                                id="overtime_weekly_anomaly_threshold_hours"
                                type="number"
                                min={0}
                                max={168}
                                step={0.5}
                                value={
                                    data.overtime_weekly_anomaly_threshold_hours
                                }
                                onChange={(event) =>
                                    setData(
                                        'overtime_weekly_anomaly_threshold_hours',
                                        event.target.valueAsNumber,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            label={t(
                                'ui.organization_settings.fields.overtime_retroactive_request_days.label',
                            )}
                            htmlFor="overtime_retroactive_request_days"
                            hint={t(
                                'ui.organization_settings.fields.overtime_retroactive_request_days.hint',
                            )}
                            error={errors.overtime_retroactive_request_days}
                        >
                            <Input
                                id="overtime_retroactive_request_days"
                                type="number"
                                min={0}
                                max={365}
                                step={1}
                                value={data.overtime_retroactive_request_days}
                                onChange={(event) =>
                                    setData(
                                        'overtime_retroactive_request_days',
                                        event.target.valueAsNumber,
                                    )
                                }
                            />
                        </FormField>
                    </div>

                    <SettingToggle
                        id="overtime_requires_pact"
                        label={t(
                            'ui.organization_settings.fields.overtime_requires_pact.label',
                        )}
                        hint={t(
                            'ui.organization_settings.fields.overtime_requires_pact.hint',
                        )}
                        checked={data.overtime_requires_pact}
                        onCheckedChange={(value) =>
                            setData('overtime_requires_pact', value)
                        }
                    />
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
