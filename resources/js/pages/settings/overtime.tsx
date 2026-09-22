import { Head, useForm } from '@inertiajs/react';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import { SettingToggle } from '@/components/settings/setting-toggle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/settings-overtime';

type Option = { value: string; label: string };

type OvertimeForm = {
    overtime_authorization_mode: string;
    overtime_weekly_anomaly_threshold_hours: number;
    overtime_retroactive_request_days: number;
    overtime_counts_pre_shift_excess: boolean;
};

type Props = {
    settings: OvertimeForm;
    overtimeAuthorizationModeOptions: Option[];
};

export default function Overtime({
    settings,
    overtimeAuthorizationModeOptions,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, patch, processing, errors } = useForm<OvertimeForm>({
        ...settings,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(update().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title={t('ui.settings.overtime.head')} />

            <h1 className="sr-only">{t('ui.settings.overtime.head')}</h1>

            <form onSubmit={submit} className="space-y-6">
                <Heading
                    variant="small"
                    title={t('ui.settings.overtime.title')}
                    description={t('ui.settings.overtime.description')}
                />

                <div className="grid gap-6 sm:grid-cols-2">
                    <FormField
                        label={t(
                            'ui.settings.overtime.fields.overtime_authorization_mode.label',
                        )}
                        htmlFor="overtime_authorization_mode"
                        hint={t(
                            'ui.settings.overtime.fields.overtime_authorization_mode.hint',
                        )}
                        error={errors.overtime_authorization_mode}
                    >
                        <Select
                            value={data.overtime_authorization_mode}
                            onValueChange={(value) =>
                                setData('overtime_authorization_mode', value)
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
                            'ui.settings.overtime.fields.overtime_weekly_anomaly_threshold_hours.label',
                        )}
                        htmlFor="overtime_weekly_anomaly_threshold_hours"
                        hint={t(
                            'ui.settings.overtime.fields.overtime_weekly_anomaly_threshold_hours.hint',
                        )}
                        error={errors.overtime_weekly_anomaly_threshold_hours}
                    >
                        <Input
                            id="overtime_weekly_anomaly_threshold_hours"
                            type="number"
                            min={0}
                            max={168}
                            step={0.5}
                            value={data.overtime_weekly_anomaly_threshold_hours}
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
                            'ui.settings.overtime.fields.overtime_retroactive_request_days.label',
                        )}
                        htmlFor="overtime_retroactive_request_days"
                        hint={t(
                            'ui.settings.overtime.fields.overtime_retroactive_request_days.hint',
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

                <div className="rounded-lg border bg-card shadow-xs">
                    <SettingToggle
                        id="overtime_counts_pre_shift_excess"
                        label={t(
                            'ui.settings.overtime.fields.overtime_counts_pre_shift_excess.label',
                        )}
                        hint={t(
                            'ui.settings.overtime.fields.overtime_counts_pre_shift_excess.hint',
                        )}
                        checked={data.overtime_counts_pre_shift_excess}
                        onCheckedChange={(value) =>
                            setData('overtime_counts_pre_shift_excess', value)
                        }
                    />
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        {t('ui.common.save')}
                    </Button>
                </div>
            </form>
        </>
    );
}
