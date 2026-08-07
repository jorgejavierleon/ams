import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import LegalHourLimitForm from '@/components/legal-hour-limit-form';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/saas/legal-hour-limits';

type Props = {
    version: {
        id: number;
        effective_from: string;
        ordinary_weekly_hours: number;
        ordinary_daily_hours: number;
        max_overtime_daily_hours: number;
        max_overtime_weekly_hours: number;
        max_total_daily_hours: number;
        max_total_weekly_hours: number;
        legal_reference: string;
        notes: string | null;
        calculated_days: number;
    };
};

export default function CorrectLegalHourLimit({ version }: Props) {
    const { t, formatDate } = useTranslations();

    const title = t('ui.saas_legal_hour_limits.correct.title', {
        date: formatDate(`${version.effective_from}T00:00:00`),
    });

    return (
        <>
            <Head title={title} />

            <div className="space-y-6">
                <Heading
                    title={title}
                    description={t(
                        'ui.saas_legal_hour_limits.correct.description',
                    )}
                />

                <LegalHourLimitForm
                    mode="correct"
                    action={update(version.id).url}
                    submitLabel={t('ui.saas_legal_hour_limits.correct.submit')}
                    calculatedDays={version.calculated_days}
                    initial={{
                        effective_from: version.effective_from,
                        ordinary_weekly_hours: String(
                            version.ordinary_weekly_hours,
                        ),
                        ordinary_daily_hours: String(
                            version.ordinary_daily_hours,
                        ),
                        max_overtime_daily_hours: String(
                            version.max_overtime_daily_hours,
                        ),
                        max_overtime_weekly_hours: String(
                            version.max_overtime_weekly_hours,
                        ),
                        max_total_daily_hours: String(
                            version.max_total_daily_hours,
                        ),
                        max_total_weekly_hours: String(
                            version.max_total_weekly_hours,
                        ),
                        legal_reference: version.legal_reference,
                        notes: version.notes ?? '',
                    }}
                />
            </div>
        </>
    );
}
