import { CheckCircle2, Clock, PenLine, Send, XCircle } from 'lucide-react';
import type { ComponentType } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

export type ActivityEntry = {
    id: number;
    event: string | null;
    title: string;
    description: string | null;
    causer: string | null;
    status_change: { from: string; to: string } | null;
    created_at: string;
};

type EventStyle = {
    icon: ComponentType<{ className?: string }>;
    dot: string;
};

/**
 * Visual treatment per activity event. Unknown events fall back to a neutral
 * clock so newly introduced events still render sensibly.
 */
const EVENT_STYLES: Record<string, EventStyle> = {
    published: {
        icon: Send,
        dot: 'bg-sky-500 text-sky-50',
    },
    signature_requested: {
        icon: PenLine,
        dot: 'bg-amber-500 text-amber-50',
    },
    signed: {
        icon: CheckCircle2,
        dot: 'bg-emerald-500 text-emerald-50',
    },
    signature_rejected: {
        icon: XCircle,
        dot: 'bg-red-500 text-red-50',
    },
};

const FALLBACK_STYLE: EventStyle = {
    icon: Clock,
    dot: 'bg-muted-foreground/70 text-background',
};

/**
 * Generic reverse-chronological audit timeline. Each entry is a node on a
 * vertical track showing the event, who caused it, an optional status
 * transition and when it happened. Entries are expected pre-sorted (newest
 * first) by the server.
 */
export function ActivityTimeline({
    activities,
}: {
    activities: ActivityEntry[];
}) {
    const { t } = useTranslations();

    if (activities.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                {t('ui.documents.activity.empty')}
            </p>
        );
    }

    return (
        <div className="relative">
            <div className="absolute top-2 bottom-2 left-[13px] w-px bg-border" />
            {activities.map((activity) => (
                <ActivityRow key={activity.id} activity={activity} />
            ))}
        </div>
    );
}

function ActivityRow({ activity }: { activity: ActivityEntry }) {
    const { t } = useTranslations();
    const style =
        (activity.event ? EVENT_STYLES[activity.event] : undefined) ??
        FALLBACK_STYLE;
    const Icon = style.icon;

    return (
        <div className="relative pb-6 pl-10 last:pb-0">
            <span
                className={cn(
                    'absolute top-0.5 left-0 grid size-[27px] place-items-center rounded-full ring-4 ring-card',
                    style.dot,
                )}
            >
                <Icon className="size-3.5" />
            </span>

            <div className="flex flex-wrap items-baseline gap-x-2">
                <span className="text-[13px] font-semibold">
                    {activity.title}
                </span>
                <span className="ml-auto text-[11px] text-muted-foreground tabular-nums">
                    {activity.created_at}
                </span>
            </div>

            {activity.description && (
                <p className="mt-0.5 text-[12.5px] text-muted-foreground">
                    {activity.description}
                </p>
            )}

            <div className="mt-1.5 flex flex-wrap items-center gap-2">
                {activity.causer && (
                    <span className="text-[11px] font-medium text-muted-foreground">
                        {activity.causer}
                    </span>
                )}
                {activity.status_change && (
                    <span className="rounded-full border px-2 py-0.5 text-[10px] font-semibold text-muted-foreground tabular-nums">
                        {t('ui.documents.activity.status_change', {
                            from: activity.status_change.from,
                            to: activity.status_change.to,
                        })}
                    </span>
                )}
            </div>
        </div>
    );
}
