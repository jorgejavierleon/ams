import type { DatesSetArg, EventClickArg, EventInput } from '@fullcalendar/core';
import { Head, useHttp } from '@inertiajs/react';
import {
    lazy,
    Suspense,
    useCallback,
    useState,
    useSyncExternalStore,
} from 'react';
import Heading from '@/components/heading';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import { useTranslations } from '@/hooks/use-translations';
import { events as eventsRoute } from '@/routes/leaves/calendar';

// FullCalendar is a client-only DOM library — loaded lazily so it never runs
// during SSR (see leaves-calendar-canvas.tsx and docs/architecture.md).
const LeavesCalendarCanvas = lazy(
    () => import('@/components/leaves-calendar-canvas'),
);

// `useSyncExternalStore` reports `false` on the server and `true` on the
// client without a state-in-effect, so the calendar only renders after hydration.
const subscribe = () => () => {};

type LeaveType = {
    value: string;
    label: string;
    color: string;
};

type LeaveEventProps = {
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    employee: string | null;
    approved_by: string | null;
    start_date: string;
    end_date: string;
};

type Props = {
    leaveTypes: LeaveType[];
};

type SelectedEvent = {
    x: number;
    y: number;
    props: LeaveEventProps;
};

export default function LeavesCalendar({ leaveTypes }: Props) {
    const { t, locale } = useTranslations();
    const { get } = useHttp({});
    const [events, setEvents] = useState<EventInput[]>([]);
    const [selected, setSelected] = useState<SelectedEvent | null>(null);
    const isClient = useSyncExternalStore(
        subscribe,
        () => true,
        () => false,
    );

    // Refetch approved leaves for the range whenever the visible dates change
    // (month/week/day navigation or view switch), without a full page reload.
    const handleDatesSet = useCallback(
        (arg: DatesSetArg) => {
            get(
                eventsRoute.url({
                    query: { start: arg.startStr, end: arg.endStr },
                }),
            )
                .then((data) => setEvents(data as EventInput[]))
                .catch(() => setEvents([]));
        },
        [get],
    );

    const handleEventClick = useCallback((arg: EventClickArg) => {
        arg.jsEvent.preventDefault();

        setSelected({
            x: arg.jsEvent.clientX,
            y: arg.jsEvent.clientY,
            props: arg.event.extendedProps as LeaveEventProps,
        });
    }, []);

    return (
        <>
            <Head title={t('ui.leaves.calendar.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.leaves.calendar.title')}
                    description={t('ui.leaves.calendar.description')}
                />

                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span className="text-sm font-medium text-muted-foreground">
                        {t('ui.leaves.calendar.legend')}:
                    </span>
                    {leaveTypes.map((type) => (
                        <span
                            key={type.value}
                            className="flex items-center gap-1.5 text-sm"
                        >
                            <span
                                aria-hidden
                                className="size-3 rounded-full"
                                style={{ backgroundColor: type.color }}
                            />
                            {type.label}
                        </span>
                    ))}
                </div>

                <div className="leaves-calendar rounded-xl border bg-card p-4 text-card-foreground">
                    {isClient ? (
                        <Suspense fallback={<CalendarSkeleton />}>
                            <LeavesCalendarCanvas
                                events={events}
                                locale={locale}
                                onDatesSet={handleDatesSet}
                                onEventClick={handleEventClick}
                            />
                        </Suspense>
                    ) : (
                        <CalendarSkeleton />
                    )}
                </div>
            </div>

            <Popover
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <PopoverAnchor asChild>
                    <span
                        aria-hidden
                        className="pointer-events-none fixed"
                        style={{ left: selected?.x ?? 0, top: selected?.y ?? 0 }}
                    />
                </PopoverAnchor>

                {selected && (
                    <PopoverContent align="start" className="w-72">
                        <dl className="grid gap-2 text-sm">
                            <DetailRow
                                label={t('ui.leaves.calendar.employee')}
                                value={
                                    selected.props.employee ??
                                    t('ui.leaves.calendar.none')
                                }
                            />
                            <DetailRow
                                label={t('ui.leaves.calendar.type')}
                                value={selected.props.type_label}
                            />
                            <DetailRow
                                label={t('ui.leaves.calendar.dates')}
                                value={`${selected.props.start_date} → ${selected.props.end_date}`}
                            />
                            <DetailRow
                                label={t('ui.leaves.calendar.approved_by')}
                                value={
                                    selected.props.approved_by ??
                                    t('ui.leaves.calendar.none')
                                }
                            />
                        </dl>
                    </PopoverContent>
                )}
            </Popover>
        </>
    );
}

function CalendarSkeleton() {
    return (
        <div className="h-[36rem] w-full animate-pulse rounded-lg bg-muted" />
    );
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-3 gap-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="col-span-2 font-medium">{value}</dd>
        </div>
    );
}
