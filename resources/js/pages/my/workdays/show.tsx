import { Head } from '@inertiajs/react';
import {
    approveModification,
    declineModification,
} from '@/actions/App/Http/Controllers/My/WorkdayController';
import WorkdayDetail from '@/components/workday-detail';
import type {
    Modification,
    WorkdayDetailData,
} from '@/components/workday-detail';
import { useTranslations } from '@/hooks/use-translations';
import { create as createOvertimeRequest } from '@/routes/my/overtime-requests';
import { index } from '@/routes/my/workdays';

type Props = {
    workday: WorkdayDetailData;
    modifications: Modification[];
    canRequestOvertime: boolean;
};

/**
 * The employee's read-only view of one of their own workdays: the same KPIs,
 * attendance strip and mark-modification timeline the admin sees, minus the
 * ability to request mark changes. Pending corrections can still be approved or
 * declined inline, since the employee is their assigned reviewer.
 */
export default function MyWorkdayShow({
    workday,
    modifications,
    canRequestOvertime,
}: Props) {
    const { t } = useTranslations();

    // KOL-79: request overtime for this specific day, pre-filled from its
    // already-calculated figure. `canRequestOvertime` already accounts for
    // the day carrying calculated overtime, so `workday.calculated_overtime`
    // is only re-checked here to satisfy the type.
    const overtimeRequest =
        canRequestOvertime && workday.calculated_overtime
            ? {
                  hours: workday.calculated_overtime,
                  href: createOvertimeRequest({
                      query: { workday: workday.id },
                  }).url,
              }
            : null;

    return (
        <>
            <Head title={workday.date_label} />

            <WorkdayDetail
                workday={workday}
                modifications={modifications}
                backHref={index().url}
                backLabel={t('ui.workdays.my.back')}
                reviewUrl={(action, modificationId) =>
                    (action === 'approve'
                        ? approveModification
                        : declineModification)([workday.id, modificationId]).url
                }
                overtimeRequest={overtimeRequest}
            />
        </>
    );
}
