import { Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Check,
    Eye,
    Lock,
    PencilLine,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

export type MarkDetails = {
    date: string;
    time: string;
    type: string;
    shift: string | null;
    employee_name: string | null;
    employee_rut: string | null;
    employer_name: string | null;
    employer_rut: string | null;
    premise_name: string | null;
    premise_address: string | null;
    coordinates: string | null;
};

export type MarkCard = {
    type: 'in' | 'out';
    time: string | null;
    scheduled: string | null;
    has_pending: boolean;
    is_modified: boolean;
    details: MarkDetails | null;
};

export type WorkdayDetailData = {
    id: number;
    date: string;
    date_label: string;
    employee: { id: number; name: string | null };
    status: string | null;
    status_label: string | null;
    status_badge: string | null;
    shift: string | null;
    shift_timeframe: string | null;
    shift_start: string | null;
    shift_end: string | null;
    premise: string | null;
    leave: { type: string; start_date: string; end_date: string } | null;
    mark_in: MarkCard;
    mark_out: MarkCard;
    worked_time: string | null;
    extra_time: string | null;
    missing_time: string | null;
};

export type Modification = {
    id: number;
    mark_type: string | null;
    mark_type_label: string | null;
    status: string | null;
    status_label: string | null;
    status_badge: string | null;
    original_time: string | null;
    modified_time: string | null;
    reason: string | null;
    notes: string | null;
    created_by: string | null;
    created_at: string | null;
    created_ago: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    reviewed_ago: string | null;
    can_review: boolean;
};

const STATUS_BADGE: Record<string, BadgeVariant> = {
    success: 'default',
    warning: 'secondary',
    destructive: 'destructive',
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

const NODE_DOT: Record<string, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    destructive: 'bg-red-500',
};

function toneChip(tone: string | null): string {
    return (
        TONE_CHIP[tone ?? ''] ?? 'bg-muted text-muted-foreground border-border'
    );
}

function badgeVariant(tone: string | null): BadgeVariant {
    return STATUS_BADGE[tone ?? ''] ?? 'outline';
}

/** Minutes since midnight for an `HH:MM` / `HH:MM:SS` string, or null. */
function toMinutes(time: string | null): number | null {
    if (!time) {
        return null;
    }

    const [hours, minutes] = time.split(':').map(Number);

    return hours * 60 + minutes;
}

/** `HH:MM` slice of a stored time, or null. */
export function hm(time: string | null): string | null {
    return time ? time.slice(0, 5) : null;
}

/** A short, signed duration label like `+22 m` or `1 h 05 m`. */
function durationLabel(totalMinutes: number): string {
    const abs = Math.abs(totalMinutes);
    const hours = Math.floor(abs / 60);
    const minutes = abs % 60;

    if (hours > 0) {
        return `${hours} h ${String(minutes).padStart(2, '0')} m`;
    }

    return `${minutes} m`;
}

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

/** A worked/extra/missing summary tile. */
function StatTile({
    label,
    value,
    sub,
    tone,
}: {
    label: string;
    value: ReactNode;
    sub?: string;
    tone?: 'ok' | 'plain';
}) {
    return (
        <div className="relative overflow-hidden rounded-xl border bg-card p-4 shadow-xs">
            {tone === 'ok' && (
                <span className="absolute inset-y-0 left-0 w-[3px] bg-emerald-500" />
            )}
            <div className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                {label}
            </div>
            <div
                className={cn(
                    'mt-1.5 text-2xl leading-none font-semibold tracking-tight tabular-nums',
                    tone === 'ok' && 'text-emerald-600 dark:text-emerald-400',
                )}
            >
                {value}
            </div>
            {sub && (
                <div className="mt-1.5 text-xs text-muted-foreground">
                    {sub}
                </div>
            )}
        </div>
    );
}

/**
 * The signature attendance strip: the scheduled shift window as a track with
 * the actual entry/exit marks plotted on it, coloured by deviation. Renders
 * only when a shift is assigned — its window is the strip's frame of reference.
 */
function AttendanceStrip({ workday }: { workday: WorkdayDetailData }) {
    const { t } = useTranslations();

    const shiftStart = toMinutes(workday.shift_start);
    const shiftEnd = toMinutes(workday.shift_end);
    const inAt = toMinutes(workday.mark_in.time);
    const outAt = toMinutes(workday.mark_out.time);

    if (shiftStart === null || shiftEnd === null) {
        return null;
    }

    const points = [shiftStart, shiftEnd, inAt, outAt].filter(
        (value): value is number => value !== null,
    );
    const lo = Math.min(...points);
    const hi = Math.max(...points);
    const rangeStart = Math.floor((lo - 20) / 60) * 60;
    const rangeEnd = Math.ceil((hi + 20) / 60) * 60;
    const span = rangeEnd - rangeStart || 60;

    const pct = (minutes: number) => ((minutes - rangeStart) / span) * 100;

    const step = span > 8 * 60 ? 120 : 60;
    const ticks: number[] = [];

    for (
        let minute = Math.ceil(rangeStart / step) * step;
        minute <= rangeEnd;
        minute += step
    ) {
        ticks.push(minute);
    }

    const marks = [
        { at: inAt, scheduled: shiftStart, late: (inAt ?? 0) > shiftStart },
        { at: outAt, scheduled: shiftEnd, late: (outAt ?? 0) < shiftEnd },
    ].filter((mark) => mark.at !== null) as {
        at: number;
        scheduled: number;
        late: boolean;
    }[];

    return (
        <section className="rounded-xl border bg-card shadow-xs">
            <div className="flex items-center justify-between border-b px-5 py-3.5">
                <h2 className="text-[13px] font-semibold">
                    {t('ui.workdays.show.attendance_title')}
                </h2>
                {workday.shift_timeframe && (
                    <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('ui.workdays.show.shift_range', {
                            range: workday.shift_timeframe,
                        })}
                    </span>
                )}
            </div>
            <div className="px-6 pt-7 pb-5">
                <div className="relative h-24">
                    {/* base rail */}
                    <div className="absolute inset-x-0 top-[46px] h-1 rounded-full bg-muted" />
                    {/* shift window */}
                    <div
                        className="absolute top-[42px] h-3 rounded-full bg-zinc-300 dark:bg-zinc-600"
                        style={{
                            left: `${pct(shiftStart)}%`,
                            width: `${pct(shiftEnd) - pct(shiftStart)}%`,
                        }}
                    />
                    <span
                        className="absolute top-2 -translate-x-1/2 text-[11px] font-semibold whitespace-nowrap text-muted-foreground"
                        style={{ left: `${pct(shiftStart)}%` }}
                    >
                        {t('ui.workdays.show.strip.entry')}
                    </span>
                    <span
                        className="absolute top-2 -translate-x-1/2 text-[11px] font-semibold whitespace-nowrap text-muted-foreground"
                        style={{ left: `${pct(shiftEnd)}%` }}
                    >
                        {t('ui.workdays.show.strip.exit')}
                    </span>

                    {ticks.map((tick) => (
                        <span
                            key={tick}
                            className="absolute top-[62px] -translate-x-1/2 text-[11px] text-muted-foreground/70 tabular-nums"
                            style={{ left: `${pct(tick)}%` }}
                        >
                            {`${String(Math.floor(tick / 60)).padStart(2, '0')}:${String(tick % 60).padStart(2, '0')}`}
                        </span>
                    ))}

                    {marks.map((mark) => {
                        const delta = mark.at - mark.scheduled;
                        const isAmber = mark.late;

                        return (
                            <div
                                key={mark.at}
                                className="absolute top-[34px] -translate-x-1/2 text-center"
                                style={{ left: `${pct(mark.at)}%` }}
                            >
                                <div
                                    className={cn(
                                        'mx-auto size-3.5 rounded-full ring-4 ring-card',
                                        isAmber
                                            ? 'bg-amber-500'
                                            : 'bg-emerald-500',
                                    )}
                                />
                                <div className="mt-2 text-[15px] font-semibold tracking-tight tabular-nums">
                                    {`${String(Math.floor(mark.at / 60)).padStart(2, '0')}:${String(mark.at % 60).padStart(2, '0')}`}
                                </div>
                                {delta !== 0 && (
                                    <div
                                        className={cn(
                                            'text-[11px] font-semibold',
                                            isAmber
                                                ? 'text-amber-600 dark:text-amber-400'
                                                : 'text-emerald-600 dark:text-emerald-400',
                                        )}
                                    >
                                        {delta > 0 ? '+' : '−'}
                                        {durationLabel(delta)}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>

                <div className="mt-3.5 flex flex-wrap gap-x-5 gap-y-1.5 text-[11.5px] text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2.5 rounded-full bg-zinc-300 dark:bg-zinc-600" />
                        {t('ui.workdays.show.strip.legend_shift')}
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2.5 rounded-full bg-amber-500" />
                        {t('ui.workdays.show.strip.legend_late')}
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2.5 rounded-full bg-emerald-500" />
                        {t('ui.workdays.show.strip.legend_extra')}
                    </span>
                </div>
            </div>
        </section>
    );
}

/** Deviation of an actual mark from its scheduled time, as label + tone. */
function markDeviation(
    mark: MarkCard,
    t: ReturnType<typeof useTranslations>['t'],
): { label: string; className: string } | null {
    const at = toMinutes(mark.time);
    const scheduled = toMinutes(mark.scheduled);

    if (at === null || scheduled === null) {
        return null;
    }

    const delta = at - scheduled;

    if (delta === 0) {
        return {
            label: t('ui.workdays.show.delta.on_time'),
            className: 'text-emerald-600 dark:text-emerald-400',
        };
    }

    // Late entry / early exit read as amber; overtime / early entry as neutral-good.
    const isAmber = mark.type === 'in' ? delta > 0 : delta < 0;
    let suffix: string;

    if (mark.type === 'in') {
        suffix =
            delta > 0
                ? t('ui.workdays.show.delta.late')
                : t('ui.workdays.show.delta.early');
    } else {
        suffix =
            delta > 0
                ? t('ui.workdays.show.delta.extra')
                : t('ui.workdays.show.delta.early');
    }

    return {
        label: `${delta > 0 ? '+' : '−'}${durationLabel(delta)} ${suffix}`,
        className: isAmber
            ? 'text-amber-600 dark:text-amber-400'
            : 'text-emerald-600 dark:text-emerald-400',
    };
}

function MarkPanel({
    mark,
    onView,
    onModify,
}: {
    mark: MarkCard;
    onView: () => void;
    onModify?: () => void;
}) {
    const { t } = useTranslations();
    const deviation = markDeviation(mark, t);
    const time = hm(mark.time);

    return (
        <div className="rounded-xl border bg-card p-4 shadow-xs">
            <div className="flex items-center justify-between">
                <span className="inline-flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                    {mark.type === 'in' ? (
                        <ArrowDown className="size-4" />
                    ) : (
                        <ArrowUp className="size-4" />
                    )}
                    {mark.type === 'in'
                        ? t('ui.workdays.show.mark_in')
                        : t('ui.workdays.show.mark_out')}
                </span>
                {mark.has_pending ? (
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                            toneChip('warning'),
                        )}
                    >
                        <span className="size-1.5 rounded-full bg-current" />
                        {t('ui.workdays.show.pending_badge')}
                    </span>
                ) : (
                    mark.is_modified && (
                        <span
                            className={cn(
                                'inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                                'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-400',
                            )}
                        >
                            {t('ui.workdays.show.modified_badge')}
                        </span>
                    )
                )}
            </div>

            <div className="mt-2 text-3xl font-semibold tracking-tight tabular-nums">
                {time ?? (
                    <span className="text-base font-normal text-muted-foreground italic">
                        {t('ui.workdays.show.no_mark')}
                    </span>
                )}
            </div>

            {mark.scheduled && (
                <div className="mt-1 text-xs text-muted-foreground">
                    {t('ui.workdays.show.scheduled')}{' '}
                    <span className="font-semibold text-foreground tabular-nums">
                        {mark.scheduled}
                    </span>
                    {deviation && (
                        <>
                            {' · '}
                            <span
                                className={cn(
                                    'font-medium',
                                    deviation.className,
                                )}
                            >
                                {deviation.label}
                            </span>
                        </>
                    )}
                </div>
            )}

            <div className="mt-3 flex items-center justify-between border-t pt-3">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={onView}
                    disabled={mark.details === null}
                >
                    <Eye className="size-4" />
                    {t('ui.workdays.show.view_mark')}
                </Button>
                {onModify &&
                    (mark.has_pending ? (
                        <span className="inline-flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                            <Lock className="size-3" />
                            {t('ui.workdays.show.edit_locked')}
                        </span>
                    ) : (
                        <Button variant="ghost" size="sm" onClick={onModify}>
                            <PencilLine className="size-4" />
                            {t('ui.workdays.show.modify_mark')}
                        </Button>
                    ))}
            </div>
        </div>
    );
}

/**
 * The read-only workday detail shared by the admin and employee views: identity
 * header, worked/extra/missing KPIs, the attendance strip, the two mark cards
 * and the mark-modification timeline with inline approve/decline for the
 * assigned reviewer. Callers layer their own actions on top:
 * - `headerAction` / `onModifyMark` add the admin's "modify marks" affordances;
 *   omitting both yields the employee's read-only marks.
 * - `employeeHref` links the employee name to their profile (admin only).
 * - `reviewUrl` builds the approve/decline endpoint for the current panel.
 */
export default function WorkdayDetail({
    workday,
    modifications,
    backHref,
    backLabel,
    reviewUrl,
    employeeHref,
    headerAction,
    onModifyMark,
}: {
    workday: WorkdayDetailData;
    modifications: Modification[];
    backHref: string;
    backLabel: string;
    reviewUrl: (
        action: 'approve' | 'decline',
        modificationId: number,
    ) => string;
    employeeHref?: string;
    headerAction?: ReactNode;
    onModifyMark?: () => void;
}) {
    const { t } = useTranslations();

    const [markDetails, setMarkDetails] = useState<MarkCard | null>(null);
    const [detailTarget, setDetailTarget] = useState<Modification | null>(null);
    const [reviewTarget, setReviewTarget] = useState<{
        action: 'approve' | 'decline';
        modification: Modification;
    } | null>(null);

    function submitReview() {
        if (reviewTarget === null) {
            return;
        }

        router.post(
            reviewUrl(reviewTarget.action, reviewTarget.modification.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setReviewTarget(null),
            },
        );
    }

    const initials = (workday.employee.name ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

    const hasExtra = Boolean(
        workday.extra_time && workday.extra_time !== '00:00',
    );
    const hasMissing = Boolean(
        workday.missing_time && workday.missing_time !== '00:00',
    );

    return (
        <>
            <div className="space-y-5 p-6">
                <Button
                    variant="ghost"
                    size="sm"
                    asChild
                    className="-ml-2 text-muted-foreground"
                >
                    <Link href={backHref}>
                        <ArrowLeft className="size-4" />
                        {backLabel}
                    </Link>
                </Button>

                {/* Header band */}
                <header className="flex flex-wrap items-start justify-between gap-4 border-b pb-5">
                    <div className="flex items-center gap-4">
                        <div className="grid size-13 place-items-center rounded-full border bg-muted text-lg font-semibold">
                            {initials}
                        </div>
                        <div>
                            <div className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('ui.workdays.show.eyebrow')}
                            </div>
                            <h1 className="text-2xl font-semibold tracking-tight capitalize">
                                {workday.date_label}
                            </h1>
                            <div className="mt-0.5 text-sm text-muted-foreground">
                                {workday.employee.name ? (
                                    employeeHref ? (
                                        <Link
                                            href={employeeHref}
                                            className="text-foreground underline-offset-4 hover:underline"
                                        >
                                            {workday.employee.name}
                                        </Link>
                                    ) : (
                                        <span className="text-foreground">
                                            {workday.employee.name}
                                        </span>
                                    )
                                ) : (
                                    '—'
                                )}
                                {workday.shift && ` · ${workday.shift}`}
                                {workday.premise && ` · ${workday.premise}`}
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-col items-end gap-2.5">
                        {workday.status && (
                            <span
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold capitalize',
                                    toneChip(workday.status_badge),
                                )}
                            >
                                <span className="size-1.5 rounded-full bg-current" />
                                {workday.status_label}
                            </span>
                        )}
                        {headerAction}
                    </div>
                </header>

                {/* KPI tiles */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatTile
                        label={t('ui.workdays.show.worked')}
                        value={workday.worked_time ?? '—'}
                        sub={
                            workday.shift_timeframe
                                ? t('ui.workdays.show.shift_range', {
                                      range: workday.shift_timeframe,
                                  })
                                : t('ui.workdays.show.no_shift')
                        }
                        tone="ok"
                    />
                    <StatTile
                        label={t('ui.workdays.show.extra')}
                        value={workday.extra_time ?? '00:00'}
                        sub={t('ui.workdays.show.extra_sub')}
                        tone={hasExtra ? 'ok' : 'plain'}
                    />
                    <StatTile
                        label={t('ui.workdays.show.missing')}
                        value={
                            <span
                                className={cn(
                                    hasMissing &&
                                        'text-red-600 dark:text-red-400',
                                )}
                            >
                                {workday.missing_time ?? '00:00'}
                            </span>
                        }
                        sub={t('ui.workdays.show.missing_sub')}
                    />
                    <StatTile
                        label={t('ui.workdays.filters.premise')}
                        value={
                            <span className="text-lg font-semibold">
                                {workday.premise ??
                                    t('ui.workdays.show.no_premise')}
                            </span>
                        }
                        sub={
                            workday.leave
                                ? t('ui.workdays.show.leave_range', {
                                      type: workday.leave.type,
                                      start: workday.leave.start_date,
                                      end: workday.leave.end_date,
                                  })
                                : t('ui.workdays.show.no_leave')
                        }
                    />
                </div>

                <AttendanceStrip workday={workday} />

                {/* Marks + modification timeline */}
                <div className="grid items-start gap-4 lg:grid-cols-[340px_1fr]">
                    <div className="flex flex-col gap-3">
                        <MarkPanel
                            mark={workday.mark_in}
                            onView={() => setMarkDetails(workday.mark_in)}
                            onModify={onModifyMark}
                        />
                        <MarkPanel
                            mark={workday.mark_out}
                            onView={() => setMarkDetails(workday.mark_out)}
                            onModify={onModifyMark}
                        />
                    </div>

                    <div className="rounded-xl border bg-card shadow-xs">
                        <div className="flex items-center justify-between border-b px-5 py-3.5">
                            <h2 className="text-[13px] font-semibold">
                                {t('ui.workdays.show.history.title')}
                            </h2>
                            {modifications.length > 0 && (
                                <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    {t('ui.workdays.show.requests_count', {
                                        count: modifications.length,
                                    })}
                                </span>
                            )}
                        </div>
                        <div className="p-5">
                            {modifications.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    {t('ui.workdays.show.history.empty')}
                                </p>
                            ) : (
                                <div className="relative">
                                    <div className="absolute top-2 bottom-2 left-[9px] w-px bg-border" />
                                    {modifications.map((modification) => (
                                        <div
                                            key={modification.id}
                                            className="relative pb-6 pl-8 last:pb-0"
                                        >
                                            <span
                                                className={cn(
                                                    'absolute top-1 left-[2px] size-3.5 rounded-full ring-4 ring-card',
                                                    NODE_DOT[
                                                        modification.status_badge ??
                                                            ''
                                                    ] ?? 'bg-muted-foreground',
                                                )}
                                            />
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span
                                                    className={cn(
                                                        'rounded-full border px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase',
                                                        toneChip(
                                                            modification.status_badge,
                                                        ),
                                                    )}
                                                >
                                                    {modification.status_label}
                                                </span>
                                                <span className="text-[13px] font-semibold">
                                                    {
                                                        modification.mark_type_label
                                                    }
                                                </span>
                                                <span className="ml-auto text-xs text-muted-foreground">
                                                    {modification.reviewed_ago ??
                                                        modification.created_ago}
                                                </span>
                                            </div>

                                            <div className="my-2 flex items-center gap-2.5 text-[17px] font-semibold tracking-tight tabular-nums">
                                                <span className="text-muted-foreground/60 line-through">
                                                    {hm(
                                                        modification.original_time,
                                                    ) ??
                                                        t(
                                                            'ui.workdays.show.no_mark',
                                                        )}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    →
                                                </span>
                                                <span>
                                                    {hm(
                                                        modification.modified_time,
                                                    )}
                                                </span>
                                            </div>

                                            <div className="text-[12.5px] text-muted-foreground">
                                                {modification.created_by && (
                                                    <>
                                                        {t(
                                                            'ui.workdays.show.requested_by',
                                                        )}{' '}
                                                        <span className="font-medium text-foreground">
                                                            {
                                                                modification.created_by
                                                            }
                                                        </span>
                                                    </>
                                                )}
                                                {modification.reviewed_by && (
                                                    <>
                                                        {' · '}
                                                        {t(
                                                            'ui.workdays.show.reviewed_inline',
                                                        )}{' '}
                                                        <span className="font-medium text-foreground">
                                                            {
                                                                modification.reviewed_by
                                                            }
                                                        </span>
                                                    </>
                                                )}
                                            </div>

                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                {modification.reason && (
                                                    <span className="rounded-md bg-muted px-2 py-0.5 text-[11.5px] text-muted-foreground">
                                                        {modification.reason}
                                                    </span>
                                                )}
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setDetailTarget(
                                                            modification,
                                                        )
                                                    }
                                                    className="rounded-md px-1.5 py-0.5 text-[11.5px] font-medium text-muted-foreground hover:text-foreground"
                                                >
                                                    {t(
                                                        'ui.workdays.show.history.view_detail',
                                                    )}
                                                </button>
                                            </div>

                                            {modification.can_review && (
                                                <div className="mt-3 flex gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                                                        onClick={() =>
                                                            setReviewTarget({
                                                                action: 'approve',
                                                                modification,
                                                            })
                                                        }
                                                    >
                                                        <Check className="size-4" />
                                                        {t(
                                                            'ui.workdays.show.history.approve',
                                                        )}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setReviewTarget({
                                                                action: 'decline',
                                                                modification,
                                                            })
                                                        }
                                                    >
                                                        <X className="size-4" />
                                                        {t(
                                                            'ui.workdays.show.history.decline',
                                                        )}
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Mark legal snapshot */}
            <Dialog
                open={markDetails !== null}
                onOpenChange={(open) => !open && setMarkDetails(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.workdays.show.mark_details.title')}
                        </DialogTitle>
                    </DialogHeader>
                    {markDetails?.details && (
                        <dl className="grid grid-cols-2 gap-3">
                            <DetailRow
                                label={t('ui.workdays.show.mark_details.date')}
                                value={markDetails.details.date}
                            />
                            <DetailRow
                                label={t('ui.workdays.show.mark_details.time')}
                                value={markDetails.details.time}
                            />
                            <DetailRow
                                label={t('ui.workdays.show.mark_details.type')}
                                value={markDetails.details.type}
                            />
                            <DetailRow
                                label={t('ui.workdays.show.mark_details.shift')}
                                value={markDetails.details.shift ?? '—'}
                            />
                            <DetailRow
                                label={t(
                                    'ui.workdays.show.mark_details.employee_name',
                                )}
                                value={markDetails.details.employee_name ?? '—'}
                            />
                            <DetailRow
                                label={t(
                                    'ui.workdays.show.mark_details.employee_rut',
                                )}
                                value={markDetails.details.employee_rut ?? '—'}
                            />
                            <DetailRow
                                label={t(
                                    'ui.workdays.show.mark_details.employer_name',
                                )}
                                value={markDetails.details.employer_name ?? '—'}
                            />
                            <DetailRow
                                label={t(
                                    'ui.workdays.show.mark_details.employer_rut',
                                )}
                                value={markDetails.details.employer_rut ?? '—'}
                            />
                            <DetailRow
                                label={t(
                                    'ui.workdays.show.mark_details.premise_name',
                                )}
                                value={markDetails.details.premise_name ?? '—'}
                            />
                            <DetailRow
                                label={t(
                                    'ui.workdays.show.mark_details.premise_address',
                                )}
                                value={
                                    markDetails.details.premise_address ?? '—'
                                }
                            />
                            <DetailRow
                                label={t(
                                    'ui.workdays.show.mark_details.coordinates',
                                )}
                                value={markDetails.details.coordinates ?? '—'}
                            />
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            {/* Modification audit trail */}
            <Dialog
                open={detailTarget !== null}
                onOpenChange={(open) => !open && setDetailTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.workdays.show.detail.title')}
                        </DialogTitle>
                    </DialogHeader>
                    {detailTarget && (
                        <dl className="grid grid-cols-2 gap-3">
                            <DetailRow
                                label={t('ui.workdays.show.history.type')}
                                value={detailTarget.mark_type_label ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.show.history.status')}
                                value={
                                    <Badge
                                        variant={badgeVariant(
                                            detailTarget.status_badge,
                                        )}
                                    >
                                        {detailTarget.status_label}
                                    </Badge>
                                }
                            />
                            <DetailRow
                                label={t('ui.workdays.show.history.original')}
                                value={detailTarget.original_time ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.show.history.modified')}
                                value={detailTarget.modified_time}
                            />
                            <DetailRow
                                label={t('ui.workdays.show.detail.reason')}
                                value={detailTarget.reason ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.show.detail.created_by')}
                                value={detailTarget.created_by ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.show.detail.created_at')}
                                value={detailTarget.created_at ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.show.detail.reviewed_by')}
                                value={
                                    detailTarget.reviewed_by ??
                                    t('ui.workdays.show.detail.not_reviewed')
                                }
                            />
                            <DetailRow
                                label={t('ui.workdays.show.detail.reviewed_at')}
                                value={
                                    detailTarget.reviewed_at ??
                                    t('ui.workdays.show.detail.not_reviewed')
                                }
                            />
                            {detailTarget.notes && (
                                <div className="col-span-2 grid gap-1">
                                    <dt className="text-sm text-muted-foreground">
                                        {t('ui.workdays.show.detail.notes')}
                                    </dt>
                                    <dd className="text-sm">
                                        {detailTarget.notes}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            {/* Approve / decline confirmation */}
            <Dialog
                open={reviewTarget !== null}
                onOpenChange={(open) => !open && setReviewTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {reviewTarget?.action === 'approve'
                                ? t('ui.workdays.show.history.approve')
                                : t('ui.workdays.show.history.decline')}
                        </DialogTitle>
                        <DialogDescription>
                            {reviewTarget?.action === 'approve'
                                ? t('ui.workdays.show.history.confirm_approve')
                                : t('ui.workdays.show.history.confirm_decline')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setReviewTarget(null)}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button
                            variant={
                                reviewTarget?.action === 'decline'
                                    ? 'destructive'
                                    : 'default'
                            }
                            onClick={submitReview}
                        >
                            {reviewTarget?.action === 'approve'
                                ? t('ui.workdays.show.history.approve')
                                : t('ui.workdays.show.history.decline')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
