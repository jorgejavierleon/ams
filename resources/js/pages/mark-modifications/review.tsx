import { Form, Head } from '@inertiajs/react';
import { Check, Clock, X } from 'lucide-react';
import {
    approve,
    decline,
} from '@/actions/App/Http/Controllers/MarkModificationReviewController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';

interface ReviewModification {
    ulid: string;
    employee_name: string;
    original_date_time: string | null;
    proposed_date_time: string;
    mark_type: string | null;
    reason: string | null;
    notes: string | null;
    state: 'pending' | 'approved' | 'declined' | 'expired';
}

/**
 * Public, no-auth page where an employee reviews a requested correction to one
 * of their attendance marks and approves or declines it. Once reviewed (or once
 * the request has expired) the action buttons give way to an outcome message.
 */
export default function MarkModificationReview({
    modification,
}: {
    modification: ReviewModification;
}) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.mark_modifications.review.title')} />

            {modification.state === 'pending' ? (
                <div className="flex flex-col gap-6">
                    <SummaryCard modification={modification} t={t} />

                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Form
                            {...decline.form(modification.ulid)}
                            className="w-full"
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="lg"
                                    className="w-full"
                                    disabled={processing}
                                    data-test="decline-button"
                                >
                                    {processing ? <Spinner /> : <X />}
                                    {t('ui.mark_modifications.review.decline')}
                                </Button>
                            )}
                        </Form>

                        <Form
                            {...approve.form(modification.ulid)}
                            className="w-full"
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full"
                                    disabled={processing}
                                    data-test="approve-button"
                                >
                                    {processing ? <Spinner /> : <Check />}
                                    {t('ui.mark_modifications.review.approve')}
                                </Button>
                            )}
                        </Form>
                    </div>
                </div>
            ) : (
                <OutcomeCard state={modification.state} t={t} />
            )}
        </>
    );
}

function SummaryCard({
    modification,
    t,
}: {
    modification: ReviewModification;
    t: ReturnType<typeof useTranslations>['t'];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{modification.employee_name}</CardTitle>
                {modification.mark_type && (
                    <CardDescription>{modification.mark_type}</CardDescription>
                )}
            </CardHeader>
            <CardContent className="grid gap-4 text-sm">
                <Detail label={t('ui.mark_modifications.review.original')}>
                    {modification.original_date_time ??
                        t('ui.mark_modifications.review.no_mark')}
                </Detail>
                <Detail label={t('ui.mark_modifications.review.proposed')}>
                    <span className="font-medium text-foreground">
                        {modification.proposed_date_time}
                    </span>
                </Detail>
                {modification.reason && (
                    <Detail label={t('ui.mark_modifications.review.reason')}>
                        {modification.reason}
                    </Detail>
                )}
                {modification.notes && (
                    <Detail label={t('ui.mark_modifications.review.notes')}>
                        {modification.notes}
                    </Detail>
                )}
            </CardContent>
        </Card>
    );
}

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right">{children}</span>
        </div>
    );
}

function OutcomeCard({
    state,
    t,
}: {
    state: 'approved' | 'declined' | 'expired';
    t: ReturnType<typeof useTranslations>['t'];
}) {
    const Icon =
        state === 'approved' ? Check : state === 'declined' ? X : Clock;

    return (
        <Card>
            <CardHeader className="items-center text-center">
                <div className="mb-2 flex size-12 items-center justify-center rounded-full bg-muted">
                    <Icon className="size-6" />
                </div>
                <CardTitle data-test="outcome-title">
                    {t(`ui.mark_modifications.review.${state}_title`)}
                </CardTitle>
                <CardDescription>
                    {t(`ui.mark_modifications.review.${state}_body`)}
                </CardDescription>
            </CardHeader>
        </Card>
    );
}

MarkModificationReview.layout = {
    title: 'Revisar corrección de marca',
    description:
        'Aprueba o rechaza el cambio solicitado a tu marca de asistencia.',
};
