import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Globe } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/saas/legal-hour-limits';

/** The legal figures a version is made of, as the form edits them. */
export type LegalHourLimitFigures = {
    effective_from: string;
    ordinary_weekly_hours: string;
    ordinary_daily_hours: string;
    max_overtime_daily_hours: string;
    max_overtime_weekly_hours: string;
    max_total_daily_hours: string;
    max_total_weekly_hours: string;
    legal_reference: string;
    notes: string;
};

type FormData = LegalHourLimitFigures & {
    acknowledged_global_effect: boolean;
    reason: string;
};

type Props = {
    /**
     * `create` appends a version and demands the global-effect acknowledgement.
     * `correct` edits a recorded one through the correction flow and demands a
     * written reason — there is no plain edit mode, because a recorded figure
     * changing silently changes what every day judged against it reported.
     */
    mode: 'create' | 'correct';
    action: string;
    submitLabel: string;
    initial?: LegalHourLimitFigures;
    /** How many calculated days the correction will recalculate. */
    calculatedDays?: number;
};

const NUMERIC_FIELDS = [
    'ordinary_weekly_hours',
    'ordinary_daily_hours',
    'max_overtime_daily_hours',
    'max_overtime_weekly_hours',
    'max_total_daily_hours',
    'max_total_weekly_hours',
] as const;

export default function LegalHourLimitForm({
    mode,
    action,
    submitLabel,
    initial,
    calculatedDays = 0,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        effective_from: initial?.effective_from ?? '',
        ordinary_weekly_hours: initial?.ordinary_weekly_hours ?? '',
        ordinary_daily_hours: initial?.ordinary_daily_hours ?? '',
        max_overtime_daily_hours: initial?.max_overtime_daily_hours ?? '',
        max_overtime_weekly_hours: initial?.max_overtime_weekly_hours ?? '',
        max_total_daily_hours: initial?.max_total_daily_hours ?? '',
        max_total_weekly_hours: initial?.max_total_weekly_hours ?? '',
        legal_reference: initial?.legal_reference ?? '',
        notes: initial?.notes ?? '',
        acknowledged_global_effect: false,
        reason: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        if (mode === 'correct') {
            put(action, { preserveScroll: true });
        } else {
            post(action, { preserveScroll: true });
        }
    }

    return (
        <form onSubmit={submit} noValidate className="grid max-w-3xl gap-6">
            {mode === 'create' ? (
                <Alert>
                    <Globe />
                    <AlertTitle>
                        {t('ui.saas_legal_hour_limits.global_effect.title')}
                    </AlertTitle>
                    <AlertDescription>
                        {t('ui.saas_legal_hour_limits.global_effect.body')}
                    </AlertDescription>
                </Alert>
            ) : (
                <Alert variant={calculatedDays > 0 ? 'destructive' : 'default'}>
                    <AlertTriangle />
                    <AlertTitle>
                        {t('ui.saas_legal_hour_limits.append_only.title')}
                    </AlertTitle>
                    <AlertDescription>
                        {calculatedDays > 0
                            ? t(
                                  'ui.saas_legal_hour_limits.correct.used_warning',
                                  { count: calculatedDays },
                              )
                            : t(
                                  'ui.saas_legal_hour_limits.correct.unused_notice',
                              )}
                    </AlertDescription>
                </Alert>
            )}

            <FormField
                label={t('ui.saas_legal_hour_limits.form.effective_from')}
                htmlFor="effective_from"
                hint={t('ui.saas_legal_hour_limits.form.effective_from_hint')}
                error={errors.effective_from}
                required
                className="max-w-xs"
            >
                <Input
                    id="effective_from"
                    type="date"
                    value={data.effective_from}
                    onChange={(e) => setData('effective_from', e.target.value)}
                    autoFocus
                />
            </FormField>

            <div className="grid gap-6 sm:grid-cols-2">
                {NUMERIC_FIELDS.map((field) => (
                    <FormField
                        key={field}
                        label={t(`ui.saas_legal_hour_limits.form.${field}`)}
                        htmlFor={field}
                        error={errors[field]}
                        required
                    >
                        <Input
                            id={field}
                            type="number"
                            step="0.25"
                            min="0"
                            value={data[field]}
                            onChange={(e) => setData(field, e.target.value)}
                        />
                    </FormField>
                ))}
            </div>

            <FormField
                label={t('ui.saas_legal_hour_limits.form.legal_reference')}
                htmlFor="legal_reference"
                error={errors.legal_reference}
                required
            >
                <Input
                    id="legal_reference"
                    value={data.legal_reference}
                    onChange={(e) => setData('legal_reference', e.target.value)}
                    placeholder={t(
                        'ui.saas_legal_hour_limits.form.legal_reference_placeholder',
                    )}
                />
            </FormField>

            <FormField
                label={t('ui.saas_legal_hour_limits.form.notes')}
                htmlFor="notes"
                error={errors.notes}
            >
                <textarea
                    id="notes"
                    rows={3}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                />
            </FormField>

            {mode === 'correct' && (
                <FormField
                    label={t('ui.saas_legal_hour_limits.correct.reason')}
                    htmlFor="reason"
                    hint={t('ui.saas_legal_hour_limits.correct.reason_hint')}
                    error={errors.reason}
                    required
                >
                    <textarea
                        id="reason"
                        rows={3}
                        value={data.reason}
                        onChange={(e) => setData('reason', e.target.value)}
                        className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </FormField>
            )}

            {mode === 'create' && (
                <div className="grid gap-2">
                    <label className="flex items-start gap-2 text-sm">
                        <Checkbox
                            checked={data.acknowledged_global_effect}
                            onCheckedChange={(checked) =>
                                setData(
                                    'acknowledged_global_effect',
                                    checked === true,
                                )
                            }
                        />
                        {t(
                            'ui.saas_legal_hour_limits.global_effect.acknowledge',
                        )}
                    </label>
                    {errors.acknowledged_global_effect && (
                        <p className="text-sm text-destructive">
                            {errors.acknowledged_global_effect}
                        </p>
                    )}
                </div>
            )}

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
