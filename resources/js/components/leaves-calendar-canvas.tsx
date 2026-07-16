import type {
    DatesSetArg,
    EventClickArg,
    EventInput,
} from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';

type Props = {
    events: EventInput[];
    locale: string;
    onDatesSet: (arg: DatesSetArg) => void;
    onEventClick: (arg: EventClickArg) => void;
};

/**
 * The FullCalendar surface. FullCalendar touches the DOM at import time, so
 * this module is loaded on the client only via `React.lazy` — see the SSR note
 * in docs/architecture.md and the `MapCanvas` pattern.
 */
export default function LeavesCalendarCanvas({
    events,
    locale,
    onDatesSet,
    onEventClick,
}: Props) {
    return (
        <FullCalendar
            plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
            initialView="dayGridMonth"
            headerToolbar={{
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
            }}
            locale={locale}
            height="auto"
            firstDay={1}
            events={events}
            datesSet={onDatesSet}
            eventClick={onEventClick}
            eventDisplay="block"
            dayMaxEvents={3}
        />
    );
}
