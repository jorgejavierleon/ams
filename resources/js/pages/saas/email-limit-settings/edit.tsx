import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/saas/email-limit-settings';

type Props = {
    baseline: number | null;
};

export default function EditEmailLimitSettings({ baseline }: Props) {
    const { t } = useTranslations();
    const { data, setData, patch, processing, errors } = useForm({
        expected_emails_per_user_per_month: baseline?.toString() ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        patch(update().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title={t('ui.saas_email_limit_settings.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.saas_email_limit_settings.title')}
                    description={t('ui.saas_email_limit_settings.description')}
                />

                <Card className="max-w-sm">
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-6">
                            <FormField
                                label={t(
                                    'ui.saas_email_limit_settings.fields.expected_emails_per_user_per_month',
                                )}
                                htmlFor="expected_emails_per_user_per_month"
                                hint={t('ui.saas_email_limit_settings.hint')}
                                error={
                                    errors.expected_emails_per_user_per_month
                                }
                            >
                                <Input
                                    id="expected_emails_per_user_per_month"
                                    type="number"
                                    min={1}
                                    value={
                                        data.expected_emails_per_user_per_month
                                    }
                                    onChange={(e) =>
                                        setData(
                                            'expected_emails_per_user_per_month',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    autoFocus
                                />
                            </FormField>

                            <div>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('ui.saas_email_limit_settings.submit')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
