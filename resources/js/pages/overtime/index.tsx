import { Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    FileText,
    ListChecks,
    Plus,
    Timer,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import {
    create as createRequest,
    index as myRequestsIndex,
} from '@/routes/my/overtime-requests';
import { index as myRestDayBalanceIndex } from '@/routes/my/overtime-rest-day-balance';
import { index as pactsIndex } from '@/routes/overtime/pacts';
import { index as queueIndex } from '@/routes/overtime/queue';
import { index as restDayBalancesIndex } from '@/routes/overtime/rest-day-balances';

type Props = {
    can: {
        managePacts: boolean;
        viewQueue: boolean;
        request: boolean;
        manageRestDayBalances: boolean;
        viewOwnRestDayBalance: boolean;
    };
};

export default function OvertimeIndex({ can }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.overtime.index.title')} />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title={t('ui.overtime.index.title')}
                        description={t('ui.overtime.index.description')}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        {can.request && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={myRequestsIndex()}>
                                        <ListChecks className="size-4" />
                                        {t('ui.overtime.index.my_requests')}
                                    </Link>
                                </Button>
                                <Button asChild>
                                    <Link href={createRequest()}>
                                        <Plus className="size-4" />
                                        {t('ui.overtime.index.new_request')}
                                    </Link>
                                </Button>
                            </>
                        )}
                        {can.viewQueue && (
                            <Button variant="outline" asChild>
                                <Link href={queueIndex()}>
                                    <ListChecks className="size-4" />
                                    {t('ui.overtime.queue.title')}
                                </Link>
                            </Button>
                        )}
                        {can.managePacts && (
                            <Button variant="outline" asChild>
                                <Link href={pactsIndex()}>
                                    <FileText className="size-4" />
                                    {t('ui.overtime.pacts.title')}
                                </Link>
                            </Button>
                        )}
                        {can.manageRestDayBalances && (
                            <Button variant="outline" asChild>
                                <Link href={restDayBalancesIndex()}>
                                    <CalendarClock className="size-4" />
                                    {t('ui.overtime.rest_day_balances.title')}
                                </Link>
                            </Button>
                        )}
                        {!can.manageRestDayBalances &&
                            can.viewOwnRestDayBalance && (
                                <Button variant="outline" asChild>
                                    <Link href={myRestDayBalanceIndex()}>
                                        <CalendarClock className="size-4" />
                                        {t(
                                            'ui.overtime.rest_day_balances.my.title',
                                        )}
                                    </Link>
                                </Button>
                            )}
                    </div>
                </div>

                <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-12 text-center text-muted-foreground">
                    <Timer className="size-8" />
                    <p className="text-sm">
                        {t('ui.overtime.index.coming_soon')}
                    </p>
                </div>
            </div>
        </>
    );
}
