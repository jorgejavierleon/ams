import { Head, router, usePage } from '@inertiajs/react';
import { Check, Clock, LogIn, LogOut, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { store } from '@/routes/my/marks';

type Shift = {
    shift_id: number;
    start_time: string;
    end_time: string;
};

type Clock = {
    shift: Shift | null;
    in: string | null;
    out: string | null;
};

type DashboardProps = {
    clock: Clock | null;
};

type MarkType = 'in' | 'out';

type ClockState = 'idle' | 'working' | 'complete';

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/** Parse a "HH:MM" punch into minutes since midnight. */
function minutesOfDay(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return hours * 60 + minutes;
}

/** Format a duration in minutes as "Xh YYm". */
function formatDuration(minutes: number): string {
    const safe = Math.max(0, minutes);

    return `${Math.floor(safe / 60)}h ${pad(safe % 60)}m`;
}

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

/** The live wall-clock time, HH:MM with smaller seconds. */
function ClockTime({ now, className }: { now: Date; className?: string }) {
    return (
        <div
            className={cn(
                'font-mono font-semibold tracking-tighter tabular-nums',
                className,
            )}
        >
            {pad(now.getHours())}:{pad(now.getMinutes())}
            <span className="ml-1 align-baseline text-[0.42em] font-medium text-muted-foreground">
                {pad(now.getSeconds())}
            </span>
        </div>
    );
}

function ShiftChip({ clock }: { clock: Clock }) {
    const { t } = useTranslations();

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border bg-muted/50 px-3 py-1.5 text-xs font-medium whitespace-nowrap text-muted-foreground">
            <Clock className="size-3.5" />
            {clock.shift
                ? `${clock.shift.start_time}–${clock.shift.end_time}`
                : t('ui.marks.no_shift_chip')}
        </span>
    );
}

function StatusPill({
    state,
    statusText,
}: {
    state: ClockState;
    statusText: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2.5 rounded-full border px-3.5 py-1.5 text-sm font-medium',
                state === 'working'
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                    : 'bg-muted/50 text-muted-foreground',
            )}
        >
            <span className="relative flex size-2">
                {state === 'working' ? (
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-75" />
                ) : null}
                <span
                    className={cn(
                        'relative inline-flex size-2 rounded-full',
                        state === 'working'
                            ? 'bg-emerald-500'
                            : 'bg-muted-foreground/50',
                    )}
                />
            </span>
            <span className="tabular-nums">{statusText}</span>
        </span>
    );
}

function ActionArea({
    state,
    note,
    onClick,
}: {
    state: ClockState;
    note: string;
    onClick: () => void;
}) {
    const { t } = useTranslations();

    return (
        <div>
            <Button
                className="h-auto w-full py-3.5 text-base"
                disabled={state === 'complete'}
                onClick={onClick}
            >
                {state === 'idle' ? (
                    <LogIn className="size-5" />
                ) : state === 'working' ? (
                    <LogOut className="size-5" />
                ) : (
                    <Check className="size-5" />
                )}
                {state === 'idle'
                    ? t('ui.marks.check_in')
                    : state === 'working'
                      ? t('ui.marks.check_out')
                      : t('ui.marks.complete_cta')}
            </Button>
            <p className="mt-2.5 text-center text-xs text-muted-foreground">
                {note}
            </p>
        </div>
    );
}

function SummaryRow({
    clock,
    state,
    worked,
    className,
}: {
    clock: Clock;
    state: ClockState;
    worked: string | null;
    className?: string;
}) {
    const { t } = useTranslations();

    return (
        <div className={cn('grid grid-cols-3 [&>*+*]:border-l', className)}>
            <SummaryCell
                icon={<Check className="size-3.5" />}
                label={t('ui.marks.types.in')}
                value={clock.in}
            />
            <SummaryCell
                icon={<X className="size-3.5" />}
                label={t('ui.marks.types.out')}
                value={clock.out}
            />
            <SummaryCell
                icon={<Clock className="size-3.5" />}
                label={t('ui.marks.worked')}
                value={
                    worked ??
                    (state === 'working' ? t('ui.marks.in_progress') : null)
                }
                accent={worked !== null}
            />
        </div>
    );
}

/**
 * The attendance widget as a punch clock: the live time is the hero, a single
 * context-aware action advances the workday (entry → exit → complete), and a
 * summary row reads back entry, exit, and total worked at a glance. The clock
 * and elapsed timer are computed client-side; punches are still registered
 * server-side with the server's time via {@link store}.
 */
function ClockCard({ clock }: { clock: Clock }) {
    const { t, formatDate } = useTranslations();
    const { auth } = usePage().props;
    const [pending, setPending] = useState<MarkType | null>(null);
    const [processing, setProcessing] = useState(false);
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 1000);

        return () => clearInterval(id);
    }, []);

    const state: ClockState =
        clock.in === null
            ? 'idle'
            : clock.out === null
              ? 'working'
              : 'complete';

    const nextType: MarkType = state === 'working' ? 'out' : 'in';

    const nowMinutes = now.getHours() * 60 + now.getMinutes();
    const elapsed =
        clock.in !== null
            ? formatDuration(nowMinutes - minutesOfDay(clock.in))
            : null;
    const worked =
        clock.in !== null && clock.out !== null
            ? formatDuration(minutesOfDay(clock.out) - minutesOfDay(clock.in))
            : null;

    const firstName = auth.user.name.split(' ')[0];
    // Re-derive the label only when the calendar day changes, not every tick.
    const dayKey = now.getDate();
    const today = useMemo(
        () =>
            capitalize(
                formatDate(new Date(), {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                }),
            ),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [formatDate, dayKey],
    );

    const statusText =
        state === 'idle'
            ? t('ui.marks.status.idle')
            : state === 'working'
              ? t('ui.marks.status.working', { elapsed: elapsed ?? '' })
              : t('ui.marks.status.complete');

    const note =
        state === 'idle'
            ? t('ui.marks.note.idle')
            : state === 'working'
              ? t('ui.marks.note.working')
              : t('ui.marks.note.complete');

    function submit() {
        if (pending === null) {
            return;
        }

        const type = pending;

        router.post(
            store().url,
            { type },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setPending(null);
                },
            },
        );
    }

    const greeting = (
        <div>
            <p className="font-semibold tracking-tight">
                {t('ui.marks.greeting', { name: firstName })}
            </p>
            <p className="mt-0.5 text-sm text-muted-foreground">{today}</p>
        </div>
    );

    return (
        <Card className="w-full max-w-md gap-0 self-start overflow-hidden p-0 xl:max-w-none">
            {/* Vertical card — phones and tablets (unchanged) */}
            <div className="flex flex-col xl:hidden">
                <div className="flex items-start justify-between gap-4 px-6 pt-6">
                    {greeting}
                    <ShiftChip clock={clock} />
                </div>

                <div className="px-6 pt-6 text-center">
                    <ClockTime now={now} className="text-6xl sm:text-7xl" />
                    <div className="mt-4">
                        <StatusPill state={state} statusText={statusText} />
                    </div>
                </div>

                <div className="px-6 pt-6">
                    <ActionArea
                        state={state}
                        note={note}
                        onClick={() => setPending(nextType)}
                    />
                </div>

                <SummaryRow
                    clock={clock}
                    state={state}
                    worked={worked}
                    className="mt-6 border-t"
                />
            </div>

            {/* Horizontal bar — desktop, spanning the dashboard body */}
            <div className="hidden w-full items-stretch xl:grid xl:grid-cols-[minmax(210px,1fr)_auto_minmax(240px,1fr)_auto]">
                <div className="flex flex-col justify-center gap-3.5 p-7">
                    {greeting}
                    <div className="flex flex-col items-start gap-3">
                        <ShiftChip clock={clock} />
                        <StatusPill state={state} statusText={statusText} />
                    </div>
                </div>

                <div className="flex flex-col items-center justify-center gap-2 border-l px-9 py-7 text-center">
                    <ClockTime now={now} className="text-6xl" />
                    <div className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('ui.marks.current_time')}
                    </div>
                </div>

                <div className="flex flex-col justify-center border-l px-7 py-7">
                    <ActionArea
                        state={state}
                        note={note}
                        onClick={() => setPending(nextType)}
                    />
                </div>

                <SummaryRow
                    clock={clock}
                    state={state}
                    worked={worked}
                    className="border-l"
                />
            </div>

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPending(null);
                    }
                }}
                title={
                    pending === 'out'
                        ? t('ui.marks.confirm.check_out_title')
                        : t('ui.marks.confirm.check_in_title')
                }
                description={t('ui.marks.confirm.description')}
                confirmLabel={t('ui.marks.confirm.action')}
                variant="default"
                onConfirm={submit}
                processing={processing}
            />
        </Card>
    );
}

function SummaryCell({
    icon,
    label,
    value,
    accent = false,
}: {
    icon: React.ReactNode;
    label: string;
    value: string | null;
    accent?: boolean;
}) {
    return (
        <div className="flex flex-col justify-center px-2.5 py-4 text-center">
            <div className="flex items-center justify-center gap-1.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                {icon}
                {label}
            </div>
            <div
                className={cn(
                    'mt-1.5 font-mono text-xl font-semibold tabular-nums',
                    value === null && 'text-muted-foreground/60',
                    accent && 'text-primary',
                )}
            >
                {value ?? '—'}
            </div>
        </div>
    );
}

export default function Dashboard({ clock }: DashboardProps) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.dashboard.title')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {clock ? (
                    <ClockCard clock={clock} />
                ) : (
                    <>
                        <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                            <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                            </div>
                            <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                            </div>
                            <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                            </div>
                        </div>
                        <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
