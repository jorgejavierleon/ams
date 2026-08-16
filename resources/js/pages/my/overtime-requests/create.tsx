import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { decimalHoursToTime } from '@/lib/overtime-duration';
import { index, store } from '@/routes/my/overtime-requests';

type Props = {
    retroactiveWindowDays: number;
};

type OvertimeRequestForm = {
    date: string;
    requested_hours: string;
    reason: string;
};

export default function CreateMyOvertimeRequest({
    retroactiveWindowDays,
}: Props) {
    const { t } = useTranslations();

    const { data, setData, transform, post, processing, errors } =
        useForm<OvertimeRequestForm>({
            date: '',
            requested_hours: '',
            reason: '',
        });

    function submit(event: FormEvent) {
        event.preventDefault();
        transform((data) => ({
            ...data,
            requested_hours: decimalHoursToTime(data.requested_hours),
        }));
        post(store().url);
    }

    return (
        <>
            <Head title={t('ui.overtime.requests.my.create.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title={t('ui.overtime.requests.my.create.title')}
                        description={t(
                            'ui.overtime.requests.my.create.description',
                        )}
                    />
                </div>

                <form
                    onSubmit={submit}
                    noValidate
                    className="grid max-w-4xl gap-6"
                >
                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            label={t('ui.overtime.requests.my.form.date')}
                            htmlFor="date"
                            required
                            error={errors.date}
                            hint={t(
                                'ui.overtime.requests.my.form.retroactive_hint',
                                { days: String(retroactiveWindowDays) },
                            )}
                        >
                            <Input
                                id="date"
                                type="date"
                                value={data.date}
                                onChange={(event) =>
                                    setData('date', event.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t(
                                'ui.overtime.requests.my.form.requested_hours',
                            )}
                            htmlFor="requested_hours"
                            required
                            error={errors.requested_hours}
                            hint={t(
                                'ui.overtime.requests.my.form.requested_hours_hint',
                            )}
                        >
                            <Input
                                id="requested_hours"
                                type="number"
                                min="0.25"
                                step="0.25"
                                className="w-28"
                                value={data.requested_hours}
                                onChange={(event) =>
                                    setData(
                                        'requested_hours',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.overtime.requests.my.form.reason')}
                            htmlFor="reason"
                            error={errors.reason}
                            className="sm:col-span-2"
                        >
                            <textarea
                                id="reason"
                                rows={3}
                                value={data.reason}
                                onChange={(event) =>
                                    setData('reason', event.target.value)
                                }
                                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </FormField>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {t('ui.overtime.requests.my.create.submit')}
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>{t('ui.common.cancel')}</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
