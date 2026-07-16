import { Head, router } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    CircleAlert,
    Clock,
    LogIn,
    LogOut,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    approveModification,
    declineModification,
} from '@/actions/App/Http/Controllers/My/WorkdayController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/my/workdays';

type PendingModification = {
    id: number;
    workday_id: number;
    date_label: string | null;
    mark_type: 'in' | 'out' | null;
    mark_type_label: string | null;
    original_time: string | null;
    proposed_time: string;
    reason: string | null;
    notes: string | null;
    requested_by: string | null;
    created_ago: string | null;
    is_expired: boolean;
};

type Workday = {
    id: number;
    date: string;
    date_label: string;
    weekday: string;
    status: string | null;
    status_label: string | null;
    status_badge: string | null;
    mark_in_at: string | null;
    mark_out_at: string | null;
    worked_time: string | null;
    shift: string | null;
    leave_type: string | null;
    pending_modifications: number;
};

type Props = {
    workdays: Workday[];
    pendingModifications: PendingModification[];
    filters: {
        from: string;
        to: string;
    };
};

/** Soft, tinted status chips keyed by the shared semantic tone. */
const TONE_CHIP: Record<string, string> = {
    success:
        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/60',
    warning:
        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900/60',
    destructive:
        'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/40 dark:text-red-400 dark:border-red-900/60',
};

function toneChip(tone: string | null): string {
    return (
        TONE_CHIP[tone ?? ''] ?? 'bg-muted text-muted-foreground border-border'
    );
}

export default function MyWorkdaysIndex({
    workdays,
    pendingModifications,
    filters,
}: Props) {
    const { t } = useTranslations();

    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function applyRange(nextFrom: string, nextTo: string) {
        router.get(
            index().url,
            { from: nextFrom, to: nextTo },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['workdays', 'filters'],
            },
        );
    }

    function resolve(
        modification: PendingModification,
        action: 'approve' | 'decline',
    ) {
        const helper =
            action === 'approve' ? approveModification : declineModification;

        router.post(
            helper([modification.workday_id, modification.id]).url,
            {},
            {
                preserveScroll: true,
                // Drop the card the instant the employee acts; Inertia rolls the
                // list back automatically if the request fails.
                optimistic: (props) => ({
                    pendingModifications: (
                        props.pendingModifications as PendingModification[]
                    ).filter((item) => item.id !== modification.id),
                }),
            },
        );
    }

    return (
        <>
            <Head title={t('ui.workdays.my.title')} />

            <div className="space-y-8 p-6">
                <Heading
                    title={t('ui.workdays.my.title')}
                    description={t('ui.workdays.my.description')}
                />

                {pendingModifications.length > 0 && (
                    <section className="rounded-xl border border-amber-200 bg-amber-50/50 p-5 dark:border-amber-900/60 dark:bg-amber-950/20">
                        <div className="flex items-center gap-3">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400">
                                <CircleAlert className="size-5" />
                            </div>
                            <div className="flex-1">
                                <h2 className="font-semibold">
                                    {t('ui.workdays.my.pending.title')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {t('ui.workdays.my.pending.subtitle')}
                                </p>
                            </div>
                            <span className="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-700 tabular-nums dark:bg-amber-900/50 dark:text-amber-400">
                                {t('ui.workdays.my.pending.count', {
                                    count: String(pendingModifications.length),
                                })}
                            </span>
                        </div>

                        <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {pendingModifications.map((modification) => (
                                <CorrectionCard
                                    key={modification.id}
                                    modification={modification}
                                    onApprove={() =>
                                        resolve(modification, 'approve')
                                    }
                                    onDecline={() =>
                                        resolve(modification, 'decline')
                                    }
                                    t={t}
                                />
                            ))}
                        </div>
                    </section>
                )}

                <section className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold">
                            {t('ui.workdays.my.list.title')}
                        </h2>
                        <div className="flex items-center gap-2">
                            <input
                                type="date"
                                value={from}
                                max={to || undefined}
                                onChange={(event) => {
                                    setFrom(event.target.value);
                                    applyRange(event.target.value, to);
                                }}
                                aria-label={t('ui.workdays.my.filters.from')}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            />
                            <span className="text-muted-foreground">–</span>
                            <input
                                type="date"
                                value={to}
                                min={from || undefined}
                                onChange={(event) => {
                                    setTo(event.target.value);
                                    applyRange(from, event.target.value);
                                }}
                                aria-label={t('ui.workdays.my.filters.to')}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            />
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('ui.workdays.my.columns.date')}
                                    </TableHead>
                                    <TableHead>
                                        {t('ui.workdays.my.columns.status')}
                                    </TableHead>
                                    <TableHead className="text-center">
                                        {t('ui.workdays.my.columns.mark_in')}
                                    </TableHead>
                                    <TableHead className="text-center">
                                        {t('ui.workdays.my.columns.mark_out')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('ui.workdays.my.columns.worked')}
                                    </TableHead>
                                    <TableHead>
                                        {t('ui.workdays.my.columns.shift')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {workdays.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {t('ui.workdays.my.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    workdays.map((workday) => (
                                        <TableRow
                                            key={workday.id}
                                            onClick={() =>
                                                router.visit(
                                                    show(workday.id).url,
                                                )
                                            }
                                            className={cn(
                                                'cursor-pointer',
                                                workday.pending_modifications >
                                                    0 &&
                                                    'bg-amber-50/40 dark:bg-amber-950/10',
                                            )}
                                        >
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {workday.pending_modifications >
                                                        0 && (
                                                        <span
                                                            className="size-1.5 shrink-0 rounded-full bg-amber-500"
                                                            title={t(
                                                                'ui.workdays.my.list.pending_flag',
                                                            )}
                                                        />
                                                    )}
                                                    <div>
                                                        <div className="font-medium capitalize">
                                                            {workday.weekday}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {workday.date}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {workday.status_label && (
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium',
                                                            toneChip(
                                                                workday.status_badge,
                                                            ),
                                                        )}
                                                    >
                                                        {workday.status_label}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-center tabular-nums">
                                                {workday.mark_in_at ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-center tabular-nums">
                                                {workday.mark_out_at ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {workday.worked_time ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {workday.leave_type ??
                                                    workday.shift ??
                                                    '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </>
    );
}

function CorrectionCard({
    modification,
    onApprove,
    onDecline,
    t,
}: {
    modification: PendingModification;
    onApprove: () => void;
    onDecline: () => void;
    t: ReturnType<typeof useTranslations>['t'];
}) {
    const MarkIcon = modification.mark_type === 'out' ? LogOut : LogIn;

    return (
        <div className="flex flex-col gap-4 rounded-lg border bg-background p-4 shadow-xs">
            <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center gap-1 rounded-md border bg-muted px-2 py-0.5 text-xs font-medium">
                        <MarkIcon className="size-3" />
                        {modification.mark_type_label}
                    </span>
                    <span className="text-sm font-medium capitalize">
                        {modification.date_label}
                    </span>
                </div>
                {modification.created_ago && (
                    <span className="shrink-0 text-xs text-muted-foreground">
                        {modification.created_ago}
                    </span>
                )}
            </div>

            <div className="flex items-center gap-3">
                <TimeTile
                    label={t('ui.workdays.my.pending.original')}
                    time={
                        modification.original_time ??
                        t('ui.workdays.my.pending.no_mark')
                    }
                    muted
                />
                <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                <TimeTile
                    label={t('ui.workdays.my.pending.proposed')}
                    time={modification.proposed_time}
                />
            </div>

            <dl className="space-y-1 text-sm">
                {modification.reason && (
                    <div className="flex gap-2">
                        <dt className="text-muted-foreground">
                            {t('ui.workdays.my.pending.reason')}:
                        </dt>
                        <dd className="font-medium">{modification.reason}</dd>
                    </div>
                )}
                {modification.notes && (
                    <p className="rounded-md bg-muted px-3 py-2 text-muted-foreground italic">
                        “{modification.notes}”
                    </p>
                )}
                {modification.requested_by && (
                    <p className="text-xs text-muted-foreground">
                        {t('ui.workdays.my.pending.requested_by', {
                            name: modification.requested_by,
                        })}
                    </p>
                )}
            </dl>

            {modification.is_expired ? (
                <div className="flex items-center gap-2 rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">
                    <Clock className="size-4" />
                    {t('ui.workdays.my.pending.expired_hint')}
                </div>
            ) : (
                <div className="mt-auto flex gap-2">
                    <Button
                        variant="outline"
                        className="flex-1"
                        onClick={onDecline}
                        data-test="decline-button"
                    >
                        <X className="size-4" />
                        {t('ui.workdays.my.pending.decline')}
                    </Button>
                    <Button
                        className="flex-1"
                        onClick={onApprove}
                        data-test="approve-button"
                    >
                        <Check className="size-4" />
                        {t('ui.workdays.my.pending.approve')}
                    </Button>
                </div>
            )}
        </div>
    );
}

function TimeTile({
    label,
    time,
    muted = false,
}: {
    label: string;
    time: string;
    muted?: boolean;
}) {
    return (
        <div className="flex-1">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div
                className={cn(
                    'rounded-md border px-3 py-1.5 text-center text-lg font-semibold tabular-nums',
                    muted
                        ? 'bg-muted text-muted-foreground'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-400',
                )}
            >
                {time}
            </div>
        </div>
    );
}
