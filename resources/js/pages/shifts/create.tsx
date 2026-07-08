import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import ShiftForm from '@/components/shift-form';
import type { Option, ShiftDayData } from '@/components/shift-form';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/shifts';

type Props = {
    types: Option[];
    defaultDays: ShiftDayData[];
    maxWeeklyHours: number;
    maxDailyHours: number;
};

export default function CreateShift({
    types,
    defaultDays,
    maxWeeklyHours,
    maxDailyHours,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.shifts.create.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.shifts.create.title')}
                    description={t('ui.shifts.create.description')}
                />

                <ShiftForm
                    types={types}
                    method="post"
                    action={store().url}
                    submitLabel={t('ui.shifts.create.submit')}
                    maxWeeklyHours={maxWeeklyHours}
                    maxDailyHours={maxDailyHours}
                    initial={{
                        name: '',
                        type: 'fixed',
                        description: '',
                        tolerance_in: '',
                        tolerance_out: '',
                        work_on_holidays: false,
                        is_archive: false,
                        is_default: false,
                        days: defaultDays,
                    }}
                />
            </div>
        </>
    );
}
