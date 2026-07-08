import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AlertError from '@/components/alert-error';
import { Combobox } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/shifts';

export type Option = { value: string; label: string };

export type ShiftDayData = {
    weekday: number;
    start_time: string;
    end_time: string;
    lunch_start_time: string;
    lunch_end_time: string;
    is_free: boolean;
};

export type ShiftFormData = {
    name: string;
    type: string;
    description: string;
    tolerance_in: string;
    tolerance_out: string;
    work_on_holidays: boolean;
    is_archive: boolean;
    is_default: boolean;
    days: ShiftDayData[];
};

type Props = {
    types: Option[];
    method: 'post' | 'patch';
    action: string;
    submitLabel: string;
    maxWeeklyHours: number;
    maxDailyHours: number;
    initial: ShiftFormData;
};

/** Worked hours for one day: (end - start) minus the lunch break. */
function dailyHours(day: ShiftDayData): number {
    if (day.is_free) {
        return 0;
    }

    const toMinutes = (value: string): number | null => {
        const [h, m] = value.split(':').map(Number);

        return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
    };

    const start = toMinutes(day.start_time);
    const end = toMinutes(day.end_time);
    const lunchStart = toMinutes(day.lunch_start_time);
    const lunchEnd = toMinutes(day.lunch_end_time);

    if (
        start === null ||
        end === null ||
        lunchStart === null ||
        lunchEnd === null
    ) {
        return 0;
    }

    return (end - start - (lunchEnd - lunchStart)) / 60;
}

function formatHours(value: number): string {
    return `${Number(value.toFixed(2))}`;
}

export default function ShiftForm({
    types,
    method,
    action,
    submitLabel,
    maxWeeklyHours,
    maxDailyHours,
    initial,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, patch, processing, errors } =
        useForm<ShiftFormData>(initial);

    const fieldErrors = errors as Record<string, string>;

    const weeklyHours = data.days.reduce(
        (sum, day) => sum + dailyHours(day),
        0,
    );
    const exceedsWeekly = weeklyHours > maxWeeklyHours;

    function updateDay<K extends keyof ShiftDayData>(
        index: number,
        field: K,
        value: ShiftDayData[K],
    ) {
        setData(
            'days',
            data.days.map((day, current) =>
                current === index ? { ...day, [field]: value } : day,
            ),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true };

        if (method === 'patch') {
            patch(action, options);
        } else {
            post(action, options);
        }
    }

    return (
        <form onSubmit={submit} noValidate className="grid max-w-4xl gap-8">
            <section className="grid gap-6">
                <h2 className="text-sm font-medium text-muted-foreground">
                    {t('ui.shifts.form.details')}
                </h2>

                <div className="grid gap-6 sm:grid-cols-2">
                    <FormField
                        label={t('ui.shifts.form.name')}
                        htmlFor="name"
                        required
                        error={errors.name}
                    >
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoFocus
                        />
                    </FormField>

                    <FormField
                        label={t('ui.shifts.form.type')}
                        htmlFor="type"
                        required
                        error={errors.type}
                    >
                        <Combobox
                            id="type"
                            options={types}
                            value={data.type}
                            onChange={(value) => setData('type', value)}
                            placeholder={t('ui.shifts.form.type_placeholder')}
                            searchPlaceholder={t('ui.shifts.form.type_search')}
                            emptyLabel={t('ui.shifts.form.type_empty')}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.shifts.form.description')}
                        htmlFor="description"
                        error={errors.description}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="description"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label={t('ui.shifts.form.tolerance_in')}
                        htmlFor="tolerance_in"
                        error={errors.tolerance_in}
                        hint={t('ui.shifts.form.tolerance_hint')}
                    >
                        <Input
                            id="tolerance_in"
                            type="number"
                            min={0}
                            max={720}
                            inputMode="numeric"
                            placeholder={t(
                                'ui.shifts.form.tolerance_placeholder',
                            )}
                            value={data.tolerance_in}
                            onChange={(e) =>
                                setData('tolerance_in', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label={t('ui.shifts.form.tolerance_out')}
                        htmlFor="tolerance_out"
                        error={errors.tolerance_out}
                        hint={t('ui.shifts.form.tolerance_hint')}
                    >
                        <Input
                            id="tolerance_out"
                            type="number"
                            min={0}
                            max={720}
                            inputMode="numeric"
                            placeholder={t(
                                'ui.shifts.form.tolerance_placeholder',
                            )}
                            value={data.tolerance_out}
                            onChange={(e) =>
                                setData('tolerance_out', e.target.value)
                            }
                        />
                    </FormField>
                </div>

                <div className="flex flex-wrap gap-6">
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.is_default}
                            onCheckedChange={(checked) =>
                                setData('is_default', checked === true)
                            }
                        />
                        {t('ui.shifts.form.is_default')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.work_on_holidays}
                            onCheckedChange={(checked) =>
                                setData('work_on_holidays', checked === true)
                            }
                        />
                        {t('ui.shifts.form.work_on_holidays')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.is_archive}
                            onCheckedChange={(checked) =>
                                setData('is_archive', checked === true)
                            }
                        />
                        {t('ui.shifts.form.is_archive')}
                    </label>
                </div>
            </section>

            <section className="grid gap-4">
                <div>
                    <h2 className="text-sm font-medium text-muted-foreground">
                        {t('ui.shifts.form.schedule')}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {t('ui.shifts.form.schedule_hint')}
                    </p>
                </div>

                {typeof errors.days === 'string' && (
                    <AlertError errors={[errors.days]} />
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-xs text-muted-foreground">
                                <th className="p-3 font-medium">
                                    {t('ui.shifts.schedule.day')}
                                </th>
                                <th className="p-3 font-medium">
                                    {t('ui.shifts.schedule.working')}
                                </th>
                                <th className="p-3 font-medium">
                                    {t('ui.shifts.schedule.start')}
                                </th>
                                <th className="p-3 font-medium">
                                    {t('ui.shifts.schedule.end')}
                                </th>
                                <th className="p-3 font-medium">
                                    {t('ui.shifts.schedule.lunch_start')}
                                </th>
                                <th className="p-3 font-medium">
                                    {t('ui.shifts.schedule.lunch_end')}
                                </th>
                                <th className="p-3 text-right font-medium">
                                    {t('ui.shifts.schedule.hours')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.days.map((day, index) => {
                                const hours = dailyHours(day);
                                const exceedsDaily = hours > maxDailyHours;

                                return (
                                    <tr
                                        key={day.weekday}
                                        className={
                                            day.is_free
                                                ? 'border-b opacity-50 last:border-0'
                                                : 'border-b last:border-0'
                                        }
                                    >
                                        <td className="p-3 font-medium">
                                            {t(
                                                `ui.shifts.weekdays.${day.weekday}`,
                                            )}
                                        </td>
                                        <td className="p-3">
                                            <Checkbox
                                                checked={!day.is_free}
                                                onCheckedChange={(checked) =>
                                                    updateDay(
                                                        index,
                                                        'is_free',
                                                        checked !== true,
                                                    )
                                                }
                                                aria-label={t(
                                                    'ui.shifts.schedule.working',
                                                )}
                                            />
                                        </td>
                                        <td className="p-3">
                                            <Input
                                                type="time"
                                                className="w-32"
                                                value={day.start_time}
                                                disabled={day.is_free}
                                                onChange={(e) =>
                                                    updateDay(
                                                        index,
                                                        'start_time',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="p-3">
                                            <Input
                                                type="time"
                                                className="w-32"
                                                value={day.end_time}
                                                disabled={day.is_free}
                                                onChange={(e) =>
                                                    updateDay(
                                                        index,
                                                        'end_time',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="p-3">
                                            <Input
                                                type="time"
                                                className="w-32"
                                                value={day.lunch_start_time}
                                                disabled={day.is_free}
                                                onChange={(e) =>
                                                    updateDay(
                                                        index,
                                                        'lunch_start_time',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="p-3">
                                            <Input
                                                type="time"
                                                className="w-32"
                                                value={day.lunch_end_time}
                                                disabled={day.is_free}
                                                onChange={(e) =>
                                                    updateDay(
                                                        index,
                                                        'lunch_end_time',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td
                                            className={
                                                exceedsDaily
                                                    ? 'p-3 text-right font-medium text-amber-600 dark:text-amber-500'
                                                    : 'p-3 text-right'
                                            }
                                            title={
                                                exceedsDaily
                                                    ? t(
                                                          'ui.shifts.schedule.exceeds_daily',
                                                          {
                                                              max: maxDailyHours,
                                                          },
                                                      )
                                                    : undefined
                                            }
                                        >
                                            {day.is_free
                                                ? '—'
                                                : formatHours(hours)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                        <tfoot>
                            <tr className="border-t bg-muted/50">
                                <td
                                    colSpan={6}
                                    className="p-3 text-right font-medium"
                                >
                                    {t('ui.shifts.schedule.weekly_total')}
                                </td>
                                <td
                                    className={
                                        exceedsWeekly
                                            ? 'p-3 text-right font-semibold text-amber-600 dark:text-amber-500'
                                            : 'p-3 text-right font-semibold'
                                    }
                                >
                                    {formatHours(weeklyHours)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p
                    className={
                        exceedsWeekly
                            ? 'text-xs text-amber-600 dark:text-amber-500'
                            : 'text-xs text-muted-foreground'
                    }
                >
                    {exceedsWeekly
                        ? t('ui.shifts.schedule.exceeds_weekly', {
                              max: maxWeeklyHours,
                          })
                        : t('ui.shifts.schedule.legal_max', {
                              max: maxWeeklyHours,
                          })}
                </p>

                {Object.keys(fieldErrors)
                    .filter((key) => /^days\.\d+\./.test(key))
                    .slice(0, 1)
                    .map((key) => (
                        <p key={key} className="text-xs text-destructive">
                            {t('ui.shifts.validation.incomplete_days')}
                        </p>
                    ))}
            </section>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
                <Button variant="ghost" asChild>
                    <Link href={index()}>{t('ui.common.cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
