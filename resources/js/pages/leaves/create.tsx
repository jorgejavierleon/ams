import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { Combobox } from '@/components/combobox';
import type { ComboboxOption } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { businessDays, index, store } from '@/routes/leaves';

type Option = { value: string; label: string };

type Props = {
    employeeOptions: ComboboxOption[];
    typeOptions: Option[];
    halfDayTypeOptions: Option[];
};

const MEDICAL_TYPE = 'medical_lead';

type LeaveForm = {
    user_id: string;
    type: string;
    start_date: string;
    end_date: string;
    half_day: boolean;
    half_day_type: string;
    business_days_requested: string;
    medical_leave_number: string;
    medical_leave_doctor: string;
    notes: string;
};

export default function CreateLeave({
    employeeOptions,
    typeOptions,
    halfDayTypeOptions,
}: Props) {
    const { t } = useTranslations();

    const { data, setData, post, processing, errors } = useForm<LeaveForm>({
        user_id: '',
        type: '',
        start_date: '',
        end_date: '',
        half_day: false,
        half_day_type: '',
        business_days_requested: '',
        medical_leave_number: '',
        medical_leave_doctor: '',
        notes: '',
    });

    const isMedical = data.type === MEDICAL_TYPE;

    // Estimate the business days from the employee's shift and the holiday
    // calendar whenever the inputs it depends on change. Half-day leaves are
    // fixed at 0.5, so they opt out. The value stays editable afterwards.
    useEffect(() => {
        if (
            data.half_day ||
            !data.user_id ||
            !data.start_date ||
            !data.end_date ||
            data.end_date < data.start_date
        ) {
            return;
        }

        let active = true;

        fetch(
            businessDays({
                query: {
                    employee: Number(data.user_id),
                    start_date: data.start_date,
                    end_date: data.end_date,
                },
            }).url,
            { headers: { Accept: 'application/json' } },
        )
            .then((response) => (response.ok ? response.json() : null))
            .then((result: { business_days: number } | null) => {
                if (active && result) {
                    setData(
                        'business_days_requested',
                        String(result.business_days),
                    );
                }
            });

        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.user_id, data.start_date, data.end_date, data.half_day]);

    function toggleHalfDay(checked: boolean) {
        if (checked) {
            // A half-day leave is a single day worth 0.5 business days.
            setData((current) => ({
                ...current,
                half_day: true,
                end_date: current.start_date,
                business_days_requested: '0.5',
            }));
        } else {
            setData((current) => ({
                ...current,
                half_day: false,
                half_day_type: '',
                business_days_requested: '',
            }));
        }
    }

    function setStartDate(value: string) {
        setData((current) => ({
            ...current,
            start_date: value,
            // Keep the range collapsed to one day while half-day is on.
            end_date: current.half_day ? value : current.end_date,
        }));
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        post(store().url);
    }

    return (
        <>
            <Head title={t('ui.leaves.create.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title={t('ui.leaves.create.title')}
                        description={t('ui.leaves.create.description')}
                    />
                </div>

                <form
                    onSubmit={submit}
                    noValidate
                    className="grid max-w-4xl gap-6"
                >
                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            label={t('ui.leaves.form.employee')}
                            htmlFor="user_id"
                            required
                            error={errors.user_id}
                        >
                            <Combobox
                                id="user_id"
                                options={employeeOptions}
                                value={data.user_id}
                                onChange={(value) => setData('user_id', value)}
                                placeholder={t(
                                    'ui.leaves.form.employee_placeholder',
                                )}
                                searchPlaceholder={t(
                                    'ui.leaves.form.employee_search',
                                )}
                                emptyLabel={t('ui.leaves.form.employee_empty')}
                            />
                        </FormField>

                        <FormField
                            label={t('ui.leaves.form.type')}
                            htmlFor="type"
                            required
                            error={errors.type}
                        >
                            <Combobox
                                id="type"
                                options={typeOptions}
                                value={data.type}
                                onChange={(value) => setData('type', value)}
                                placeholder={t(
                                    'ui.leaves.form.type_placeholder',
                                )}
                                searchPlaceholder={t(
                                    'ui.leaves.form.type_search',
                                )}
                                emptyLabel={t('ui.leaves.form.type_empty')}
                            />
                        </FormField>

                        <FormField
                            label={t('ui.leaves.form.start_date')}
                            htmlFor="start_date"
                            required
                            error={errors.start_date}
                        >
                            <Input
                                id="start_date"
                                type="date"
                                value={data.start_date}
                                onChange={(event) =>
                                    setStartDate(event.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.leaves.form.end_date')}
                            htmlFor="end_date"
                            required
                            error={errors.end_date}
                        >
                            <Input
                                id="end_date"
                                type="date"
                                value={data.end_date}
                                disabled={data.half_day}
                                onChange={(event) =>
                                    setData('end_date', event.target.value)
                                }
                            />
                        </FormField>

                        <div className="flex items-center gap-2 sm:col-span-2">
                            <Checkbox
                                id="half_day"
                                checked={data.half_day}
                                onCheckedChange={(checked) =>
                                    toggleHalfDay(checked === true)
                                }
                            />
                            <Label htmlFor="half_day" className="font-normal">
                                {t('ui.leaves.form.half_day')}
                            </Label>
                        </div>

                        {data.half_day && (
                            <FormField
                                label={t('ui.leaves.form.half_day_type')}
                                htmlFor="half_day_type"
                                required
                                error={errors.half_day_type}
                                className="sm:col-span-2"
                            >
                                <Select
                                    value={data.half_day_type}
                                    onValueChange={(value) =>
                                        setData('half_day_type', value)
                                    }
                                >
                                    <SelectTrigger id="half_day_type">
                                        <SelectValue
                                            placeholder={t(
                                                'ui.leaves.form.half_day_type_placeholder',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {halfDayTypeOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        )}

                        <FormField
                            label={t('ui.leaves.form.business_days')}
                            htmlFor="business_days_requested"
                            required
                            error={errors.business_days_requested}
                            hint={
                                data.half_day
                                    ? t(
                                          'ui.leaves.form.business_days_half_hint',
                                      )
                                    : t('ui.leaves.form.business_days_hint')
                            }
                        >
                            <Input
                                id="business_days_requested"
                                type="number"
                                min="0.5"
                                step="0.5"
                                value={data.business_days_requested}
                                disabled={data.half_day}
                                onChange={(event) =>
                                    setData(
                                        'business_days_requested',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>

                        {isMedical && (
                            <div className="grid gap-6 sm:col-span-2 sm:grid-cols-2">
                                <FormField
                                    label={t(
                                        'ui.leaves.form.medical_leave_number',
                                    )}
                                    htmlFor="medical_leave_number"
                                    error={errors.medical_leave_number}
                                >
                                    <Input
                                        id="medical_leave_number"
                                        value={data.medical_leave_number}
                                        onChange={(event) =>
                                            setData(
                                                'medical_leave_number',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>

                                <FormField
                                    label={t(
                                        'ui.leaves.form.medical_leave_doctor',
                                    )}
                                    htmlFor="medical_leave_doctor"
                                    error={errors.medical_leave_doctor}
                                >
                                    <Input
                                        id="medical_leave_doctor"
                                        value={data.medical_leave_doctor}
                                        onChange={(event) =>
                                            setData(
                                                'medical_leave_doctor',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        )}

                        <FormField
                            label={t('ui.leaves.form.notes')}
                            htmlFor="notes"
                            error={errors.notes}
                            className="sm:col-span-2"
                        >
                            <textarea
                                id="notes"
                                rows={3}
                                value={data.notes}
                                onChange={(event) =>
                                    setData('notes', event.target.value)
                                }
                                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </FormField>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {t('ui.leaves.create.submit')}
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
