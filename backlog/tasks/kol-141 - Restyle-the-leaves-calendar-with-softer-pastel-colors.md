---
id: KOL-141
title: 'Restyle the leaves calendar with softer, pastel colors'
status: To Do
assignee: []
created_date: '2026-10-04 09:33'
updated_date: '2026-10-04 09:33'
labels:
  - frontend
  - ux
dependencies: []
priority: medium
type: enhancement
ordinal: 156000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The leaves calendar (resources/js/pages/leaves/calendar.tsx, rendered via resources/js/components/leaves-calendar-canvas.tsx on @fullcalendar/react) currently uses FullCalendar's default visual styling with saturated hex colors per leave type (app/Enums/LeaveType.php: #059669, #dc2626, #d97706, #2563eb, #6b7280). The user wants a softer, more subtle visual style similar to a reference calendar UI they shared: pastel/tinted event pills on a light, airy grid, rather than solid saturated blocks. Reuse the same pastel hue family already established for dashboard widget icons (resources/js/pages/dashboard.tsx iconTones: bg-*-500/15 background + text-*-600 / dark:text-*-400 foreground, e.g. violet, blue, amber, rose, teal) so the leaves calendar matches the rest of the app's visual language instead of inventing a new palette.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Each LeaveType in app/Enums/LeaveType.php renders with a softer, pastel tone (lighter/desaturated vs. current solid hex values) instead of the current saturated hex colors
- [ ] #2 The pastel tones are drawn from the same hue family used for dashboard icon tones (violet/blue/amber/rose/teal), so the calendar visually matches the dashboard rather than introducing an unrelated palette
- [ ] #3 Calendar events render as subtly tinted, rounded pills (soft background + readable text) rather than solid blocks with FullCalendar's default square styling
- [ ] #4 The month grid, day headers, and toolbar (prev/next/today, view switcher) read as light and airy, consistent with the reference design, without regressing readability of the day numbers, 'today' highlight, or the existing event-click popover with leave details
- [ ] #5 The type legend above the calendar (ui.leaves.calendar.legend) reflects the same updated pastel colors as the calendar events
- [ ] #6 Both light and dark theme remain legible and consistent with the rest of the app's existing dark-mode handling
- [ ] #7 Existing LeaveCalendarTest coverage of the events/legend payload still passes
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
