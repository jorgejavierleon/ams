import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ChevronRight,
    Clock,
    ClipboardCheck,
    FileSignature,
    ListChecks,
    LogIn,
    LogOut,
    Minus,
    Sun,
    TrendingDown,
    TrendingUp,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, XAxis } from 'recharts';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import {
    calendar as leavesCalendar,
    index as leavesIndex,
} from '@/routes/leaves';
import { index as myDocumentsIndex } from '@/routes/my/documents';
import { store } from '@/routes/my/marks';
import { index as myWorkdaysIndex } from '@/routes/my/workdays';
import { index as overtimeRequestsIndex } from '@/routes/overtime/requests';

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

type WhosOutEntry = {
    id: number;
    user: {
        id: number;
        name: string;
        avatar: string | null;
    };
    type: string;
    type_label: string;
    return_date: string;
};

type Holiday = {
    id: number;
    name: string;
    date: string;
};

type AttendanceRate = {
    rate: number | null;
    trend: number | null;
};

type AttendanceOverviewDay = {
    date: string;
    on_time: number;
    late: number;
    absent: number;
};

type AttendanceOverview = {
    days: AttendanceOverviewDay[];
};

type DashboardProps = {
    clock: Clock | null;
    whosOut: WhosOutEntry[] | null;
    upcomingHolidays: Holiday[];
    attendanceRate: AttendanceRate | null;
    attendanceOverview: AttendanceOverview | null;
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

/** Tinted icon backgrounds for dashboard widget rows, one hue per row type. */
const iconTones = {
    violet: 'bg-violet-500/15 text-violet-600 dark:text-violet-400',
    blue: 'bg-blue-500/15 text-blue-600 dark:text-blue-400',
    amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    rose: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
    teal: 'bg-teal-500/15 text-teal-600 dark:text-teal-400',
} as const;

type IconTone = keyof typeof iconTones;

function RowIcon({
    icon: Icon,
    tone,
}: {
    icon: React.ComponentType<{ className?: string }>;
    tone: IconTone;
}) {
    return (
        <span
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-lg',
                iconTones[tone],
            )}
        >
            <Icon className="size-4.5" />
        </span>
    );
}

function ActionItemRow({
    icon,
    tone,
    label,
    count,
    href,
}: {
    icon: React.ComponentType<{ className?: string }>;
    tone: IconTone;
    label: string;
    count: number;
    href: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-muted/50"
        >
            <span className="flex items-center gap-3">
                <RowIcon icon={icon} tone={tone} />
                {label}
            </span>
            <span className="flex items-center gap-1.5">
                <Badge variant="secondary">{count}</Badge>
                <ChevronRight className="size-4 text-muted-foreground" />
            </span>
        </Link>
    );
}

/**
 * The employee's own pending self-service actions (KOL-119.1). Both counts
 * are the same shared `auth` props the sidebar badges already use — reads
 * them directly rather than issuing a new dashboard-specific query.
 */
function ActionItemsCard({
    modifications,
    signatures,
}: {
    modifications: number;
    signatures: number;
}) {
    const { t } = useTranslations();

    return (
        <Card className="h-full w-full gap-3 p-4">
            <CardHeader className="px-2.5">
                <CardTitle>{t('ui.dashboard.action_items.title')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-1 px-0">
                {modifications > 0 && (
                    <ActionItemRow
                        icon={ClipboardCheck}
                        tone="violet"
                        label={t(
                            'ui.dashboard.action_items.pending_modifications',
                        )}
                        count={modifications}
                        href={myWorkdaysIndex().url}
                    />
                )}
                {signatures > 0 && (
                    <ActionItemRow
                        icon={FileSignature}
                        tone="blue"
                        label={t(
                            'ui.dashboard.action_items.pending_signatures',
                        )}
                        count={signatures}
                        href={myDocumentsIndex().url}
                    />
                )}
            </CardContent>
        </Card>
    );
}

/**
 * What a supervisor or admin needs to decide on (KOL-119.2): pending leave
 * and overtime requests, each already scoped server-side to the viewer's
 * own team (or the whole organization for admins) exactly like their own
 * queue. Visible whenever the viewer holds authority over either queue, even
 * if one of the two counts is currently zero.
 */
function PendingApprovalsCard({
    leaves,
    overtime,
}: {
    leaves: number;
    overtime: number;
}) {
    const { t } = useTranslations();

    return (
        <Card className="h-full w-full gap-3 p-4">
            <CardHeader className="px-2.5">
                <CardTitle>
                    {t('ui.dashboard.pending_approvals.title')}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-1 px-0">
                <ActionItemRow
                    icon={Sun}
                    tone="amber"
                    label={t('ui.dashboard.pending_approvals.leaves')}
                    count={leaves}
                    href={leavesIndex().url}
                />
                <ActionItemRow
                    icon={ListChecks}
                    tone="rose"
                    label={t('ui.dashboard.pending_approvals.overtime')}
                    count={overtime}
                    href={overtimeRequestsIndex().url}
                />
            </CardContent>
        </Card>
    );
}

function WhosOutRow({ entry }: { entry: WhosOutEntry }) {
    const { t, formatDate } = useTranslations();

    return (
        <div className="flex items-center gap-3 px-2.5 py-2">
            <Avatar className="size-8">
                {entry.user.avatar ? (
                    <AvatarImage
                        src={entry.user.avatar}
                        alt={entry.user.name}
                    />
                ) : null}
                <AvatarFallback className="text-xs">
                    {entry.user.name.charAt(0).toUpperCase()}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">
                    {entry.user.name}
                </p>
                <p className="text-xs text-muted-foreground">
                    {entry.type_label}
                </p>
            </div>
            <p className="text-xs whitespace-nowrap text-muted-foreground">
                {t('ui.dashboard.whos_out.returns', {
                    date: formatDate(entry.return_date, {
                        day: 'numeric',
                        month: 'short',
                    }),
                })}
            </p>
        </div>
    );
}

/**
 * Who's currently on approved leave (KOL-119.3), scoped server-side to the
 * viewer's team (or the whole organization for admins) — the widget isn't
 * rendered at all when `entries` is null upstream; an empty array here means
 * the viewer has visibility but nobody happens to be out.
 */
function WhosOutCard({ entries }: { entries: WhosOutEntry[] }) {
    const { t } = useTranslations();

    return (
        <Card className="h-full w-full gap-3 p-4">
            <CardHeader className="flex-row items-center justify-between px-2.5">
                <CardTitle>{t('ui.dashboard.whos_out.title')}</CardTitle>
                <Link
                    href={leavesCalendar().url}
                    className="text-xs font-medium text-muted-foreground hover:text-foreground"
                >
                    {t('ui.dashboard.whos_out.view_calendar')}
                </Link>
            </CardHeader>
            <CardContent className="flex flex-col gap-1 px-0">
                {entries.length === 0 ? (
                    <p className="px-2.5 py-2 text-sm text-muted-foreground">
                        {t('ui.dashboard.whos_out.empty')}
                    </p>
                ) : (
                    entries.map((entry) => (
                        <WhosOutRow key={entry.id} entry={entry} />
                    ))
                )}
            </CardContent>
        </Card>
    );
}

/**
 * The team's attendance rate for the current week vs last week (KOL-120),
 * scoped server-side exactly like {@link WhosOutCard} — the card isn't
 * rendered at all when `attendanceRate` is null upstream. `rate` is null
 * when the visible scope has no scheduled Workday rows yet this week, shown
 * as an explicit empty state rather than a misleading 0%.
 */
function AttendanceRateCard({
    attendanceRate,
}: {
    attendanceRate: AttendanceRate;
}) {
    const { t } = useTranslations();
    const { rate, trend } = attendanceRate;

    return (
        <Card className="h-full w-full gap-3 p-4">
            <CardHeader className="px-2.5">
                <CardTitle>{t('ui.dashboard.attendance_rate.title')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-1 px-2.5">
                {rate === null ? (
                    <p className="py-2 text-sm text-muted-foreground">
                        {t('ui.dashboard.attendance_rate.empty')}
                    </p>
                ) : (
                    <>
                        <div className="flex items-baseline gap-2">
                            <span className="font-mono text-3xl font-semibold tabular-nums">
                                {rate}%
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t('ui.dashboard.attendance_rate.subtitle')}
                            </span>
                        </div>
                        <AttendanceRateTrend trend={trend} />
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function AttendanceRateTrend({ trend }: { trend: number | null }) {
    const { t } = useTranslations();

    if (trend === null) {
        return null;
    }

    if (trend === 0) {
        return (
            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                <Minus className="size-3.5" />
                {t('ui.dashboard.attendance_rate.trend_flat')}
            </span>
        );
    }

    const isUp = trend > 0;

    return (
        <span
            className={cn(
                'flex items-center gap-1 text-xs font-medium',
                isUp
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-rose-600 dark:text-rose-400',
            )}
        >
            {isUp ? (
                <TrendingUp className="size-3.5" />
            ) : (
                <TrendingDown className="size-3.5" />
            )}
            {t('ui.dashboard.attendance_rate.trend', {
                points: `${isUp ? '+' : ''}${trend}`,
            })}
        </span>
    );
}

/**
 * The team's on-time/late/absent breakdown for the last 4 weeks (KOL-121),
 * scoped server-side exactly like {@link AttendanceRateCard} — the card
 * isn't rendered at all when `attendanceOverview` is null upstream. `days`
 * comes back empty (rather than a zero-filled series) when the visible
 * scope has no computed Workday rows anywhere in the period, shown as an
 * explicit empty state.
 */
function AttendanceOverviewCard({
    attendanceOverview,
}: {
    attendanceOverview: AttendanceOverview;
}) {
    const { t, formatDate } = useTranslations();
    const { days } = attendanceOverview;

    const chartConfig: ChartConfig = {
        on_time: {
            label: t('ui.dashboard.attendance_overview.on_time'),
            color: 'var(--attendance-on-time)',
        },
        late: {
            label: t('ui.dashboard.attendance_overview.late'),
            color: 'var(--attendance-late)',
        },
        absent: {
            label: t('ui.dashboard.attendance_overview.absent'),
            color: 'var(--attendance-absent)',
        },
    };

    return (
        <Card className="h-full w-full gap-3 p-4">
            <CardHeader className="flex-row items-baseline gap-2 px-2.5">
                <CardTitle>
                    {t('ui.dashboard.attendance_overview.title')}
                </CardTitle>
                <span className="text-xs text-muted-foreground">
                    {t('ui.dashboard.attendance_overview.subtitle')}
                </span>
            </CardHeader>
            <CardContent className="px-2.5">
                {days.length === 0 ? (
                    <p className="py-2 text-sm text-muted-foreground">
                        {t('ui.dashboard.attendance_overview.empty')}
                    </p>
                ) : (
                    <ChartContainer
                        config={chartConfig}
                        className="aspect-auto h-64 w-full"
                    >
                        <BarChart data={days}>
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={24}
                                tickFormatter={(value: string) =>
                                    formatDate(`${value}T00:00:00`, {
                                        day: 'numeric',
                                        month: 'short',
                                    })
                                }
                            />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(value) =>
                                            formatDate(`${value}T00:00:00`, {
                                                day: 'numeric',
                                                month: 'long',
                                            })
                                        }
                                    />
                                }
                            />
                            <ChartLegend content={<ChartLegendContent />} />
                            <Bar
                                dataKey="on_time"
                                stackId="attendance"
                                fill="var(--color-on_time)"
                                radius={[0, 0, 6, 6]}
                            />
                            <Bar
                                dataKey="late"
                                stackId="attendance"
                                fill="var(--color-late)"
                            />
                            <Bar
                                dataKey="absent"
                                stackId="attendance"
                                fill="var(--color-absent)"
                                radius={[6, 6, 0, 0]}
                            />
                        </BarChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}

function HolidayRow({ holiday }: { holiday: Holiday }) {
    const { formatDate } = useTranslations();

    return (
        <div className="flex items-center gap-3 px-2.5 py-2">
            <RowIcon icon={CalendarDays} tone="teal" />
            <p className="min-w-0 flex-1 truncate text-sm font-medium">
                {holiday.name}
            </p>
            <p className="text-xs whitespace-nowrap text-muted-foreground">
                {formatDate(holiday.date, { day: 'numeric', month: 'short' })}
            </p>
        </div>
    );
}

/**
 * The next few upcoming holidays (KOL-119.4) — visible to every authenticated
 * user with no permission gate, unlike the other dashboard widgets.
 */
function UpcomingHolidaysCard({ holidays }: { holidays: Holiday[] }) {
    const { t } = useTranslations();

    return (
        <Card className="h-full w-full gap-3 p-4">
            <CardHeader className="px-2.5">
                <CardTitle>
                    {t('ui.dashboard.upcoming_holidays.title')}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-1 px-0">
                {holidays.length === 0 ? (
                    <p className="px-2.5 py-2 text-sm text-muted-foreground">
                        {t('ui.dashboard.upcoming_holidays.empty')}
                    </p>
                ) : (
                    holidays.map((holiday) => (
                        <HolidayRow key={holiday.id} holiday={holiday} />
                    ))
                )}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    clock,
    whosOut,
    upcomingHolidays,
    attendanceRate,
    attendanceOverview,
}: DashboardProps) {
    const { t } = useTranslations();
    const { auth } = usePage().props;
    const hasActionItems =
        auth.pendingModificationsCount > 0 || auth.pendingSignaturesCount > 0;
    // Mirrors the sidebar's own permission checks (app-sidebar.tsx) for the
    // leaves/overtime approval queues.
    const canApproveTeam =
        auth.permissions.includes('ApproveTeam:Leave') ||
        auth.permissions.includes('ApproveTeam:OvertimeAuthorization') ||
        auth.permissions.includes('Manage:OvertimeAuthorization');

    return (
        <>
            <Head title={t('ui.dashboard.title')} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {clock ? <ClockCard clock={clock} /> : null}
                <div className="grid grid-cols-12 gap-4">
                    {hasActionItems ? (
                        <div className="col-span-12 md:col-span-6 xl:col-span-3">
                            <ActionItemsCard
                                modifications={auth.pendingModificationsCount}
                                signatures={auth.pendingSignaturesCount}
                            />
                        </div>
                    ) : null}
                    {canApproveTeam ? (
                        <div className="col-span-12 md:col-span-6 xl:col-span-3">
                            <PendingApprovalsCard
                                leaves={auth.pendingLeaveRequestsCount}
                                overtime={auth.pendingOvertimeRequestsCount}
                            />
                        </div>
                    ) : null}
                    {whosOut ? (
                        <div className="col-span-12 md:col-span-6 xl:col-span-3">
                            <WhosOutCard entries={whosOut} />
                        </div>
                    ) : null}
                    {attendanceRate ? (
                        <div className="col-span-12 md:col-span-6 xl:col-span-3">
                            <AttendanceRateCard
                                attendanceRate={attendanceRate}
                            />
                        </div>
                    ) : null}
                    <div className="col-span-12 md:col-span-6 xl:col-span-6">
                        <UpcomingHolidaysCard holidays={upcomingHolidays} />
                    </div>
                    {attendanceOverview ? (
                        <div className="col-span-12">
                            <AttendanceOverviewCard
                                attendanceOverview={attendanceOverview}
                            />
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
