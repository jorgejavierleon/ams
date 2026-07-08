import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import ShiftForm from '@/components/shift-form';
import type {
    Option,
    ShiftDayData,
    ShiftFormData,
} from '@/components/shift-form';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/shifts';

type Shift = {
    id: number;
    name: string;
    type: string;
    description: string | null;
    tolerance_in: number | null;
    tolerance_out: number | null;
    work_on_holidays: boolean;
    is_archive: boolean;
    is_default: boolean;
    days: Array<{
        weekday: number;
        start_time: string | null;
        end_time: string | null;
        lunch_start_time: string | null;
        lunch_end_time: string | null;
        is_free: boolean;
    }>;
};

type Props = {
    shift: Shift;
    types: Option[];
    maxWeeklyHours: number;
    maxDailyHours: number;
};

export default function EditShift({
    shift,
    types,
    maxWeeklyHours,
    maxDailyHours,
}: Props) {
    const { t } = useTranslations();

    const days: ShiftDayData[] = shift.days.map((day) => ({
        weekday: day.weekday,
        start_time: day.start_time ?? '',
        end_time: day.end_time ?? '',
        lunch_start_time: day.lunch_start_time ?? '',
        lunch_end_time: day.lunch_end_time ?? '',
        is_free: day.is_free,
    }));

    const initial: ShiftFormData = {
        name: shift.name,
        type: shift.type,
        description: shift.description ?? '',
        tolerance_in:
            shift.tolerance_in !== null ? String(shift.tolerance_in) : '',
        tolerance_out:
            shift.tolerance_out !== null ? String(shift.tolerance_out) : '',
        work_on_holidays: shift.work_on_holidays,
        is_archive: shift.is_archive,
        is_default: shift.is_default,
        days,
    };

    return (
        <>
            <Head title={t('ui.shifts.edit.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.shifts.edit.title')}
                    description={t('ui.shifts.edit.description')}
                />

                <ShiftForm
                    types={types}
                    method="patch"
                    action={update(shift.id).url}
                    submitLabel={t('ui.shifts.edit.submit')}
                    maxWeeklyHours={maxWeeklyHours}
                    maxDailyHours={maxDailyHours}
                    initial={initial}
                />
            </div>
        </>
    );
}
