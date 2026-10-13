---
id: KOL-141
title: 'Restyle the leaves calendar with softer, pastel colors'
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-10-04 09:33'
updated_date: '2026-10-13 09:47'
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
- [x] #1 Each LeaveType in app/Enums/LeaveType.php renders with a softer, pastel tone (lighter/desaturated vs. current solid hex values) instead of the current saturated hex colors
- [x] #2 The pastel tones are drawn from the same hue family used for dashboard icon tones (violet/blue/amber/rose/teal), so the calendar visually matches the dashboard rather than introducing an unrelated palette
- [x] #3 Calendar events render as subtly tinted, rounded pills (soft background + readable text) rather than solid blocks with FullCalendar's default square styling
- [x] #4 The month grid, day headers, and toolbar (prev/next/today, view switcher) read as light and airy, consistent with the reference design, without regressing readability of the day numbers, 'today' highlight, or the existing event-click popover with leave details
- [x] #5 The type legend above the calendar (ui.leaves.calendar.legend) reflects the same updated pastel colors as the calendar events
- [x] #6 Both light and dark theme remain legible and consistent with the rest of the app's existing dark-mode handling
- [x] #7 Existing LeaveCalendarTest coverage of the events/legend payload still passes
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. LeaveType enum: map each case to a Tailwind hue (Vacation=teal, Medical=rose, Unpaid=amber, Paid=blue, Other=violet); change color() to return the hue's pastel -100 hex (event/legend background); add textColor() returning the hue's -700 hex (readable event text + legend dot outline). Update legendOptions() to include text_color.
2. LeaveCalendarController::events(): add 'textColor' => $leave->type->textColor() alongside existing 'color'.
3. resources/js/pages/leaves/calendar.tsx: extend LeaveType frontend type with textColor; restyle legend dot to keep the pastel fill visible (thin border using textColor).
4. resources/css/app.css .leaves-calendar: pill-shaped events (border-radius, padding, no border) and an airier ghost-style toolbar (transparent/outlined buttons using existing theme tokens so light/dark both stay consistent).
5. Verify in browser (both themes) that events, legend, toolbar, today highlight, and the event-click popover all still read correctly.
6. Run LeaveCalendarTest, vendor/bin/pint --dirty, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Mapped each LeaveType to a Tailwind hue matching dashboard iconTones (Vacation=teal, Medical=rose, Unpaid=amber, Paid=blue, Other=violet). color() now returns the hue's -100 pastel hex, new textColor() returns the hue's -700 hex for readable pill/legend text. Controller sends both to FullCalendar's per-event color/textColor fields. CSS: pill-shaped events (border-radius: 9999px, no border) and an airy ghost-style toolbar (ported button vars to theme tokens instead of solid primary). Legend dot kept a thin border in textColor so the light pastel fill stays visible against the page background.
Verified via Chrome DevTools MCP logged in as admin@example.com on the local dev server: events render as pastel pills with readable text, legend dots match, today highlight and the event-click popover both still work, toolbar reads light/airy, and both light and dark theme (toggled via /settings/appearance) are legible.
sail artisan test --compact --filter=LeaveCalendarTest: 9 passed, 38 assertions (added assertions for the new textColor field). sail php vendor/bin/pint --dirty: clean. npm run types:check: clean. Did not run the full Pest suite per project convention (filtered run only during ticket work).

Code review (background) flagged a real dark-mode bug: --accent and --muted resolve to the identical oklch value in dark mode, so the active view-switcher button (Month/Week/Day) was visually indistinguishable from a merely-hovered one. Fixed by giving .fc-button-active its own explicit var(--primary)/var(--primary-foreground) styling instead of relying on the colliding --accent token; re-verified via hover in the browser that active vs. hover are now visually distinct in dark mode. Also tightened the LeaveType::color()/textColor() docblock to not overclaim per-theme adaptiveness (it's a fixed hex in both themes, same approach as the existing --attendance-* chart tokens) — reviewer's second finding, lower-confidence/doc-only. Re-ran pint (clean) and LeaveCalendarTest (9 passed) after the fixes.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Restyled the leaves calendar (resources/js/pages/leaves/calendar.tsx + leaves-calendar-canvas.tsx) from saturated hex event blocks to pastel pills. LeaveType enum (app/Enums/LeaveType.php) now maps each type to a violet/blue/amber/rose/teal hue matching the dashboard's iconTones, exposing a pastel -100 color() for backgrounds and a new -700 textColor() for readable text; LeaveCalendarController sends both to FullCalendar per-event. CSS (resources/css/app.css .leaves-calendar) gives events a pill shape and the toolbar an airy ghost-button treatment using theme tokens. Verified in-browser (admin@example.com, local dev server) in both light and dark mode: events, legend, today highlight, and the event-click popover all read correctly. A code review caught a dark-mode-only bug where the active view-switcher button was indistinguishable from a hovered one (--accent/--muted collide in dark mode); fixed with an explicit .fc-button-active rule and re-verified by hovering in the browser. Tests: sail artisan test --filter=LeaveCalendarTest (9 passed, including new textColor assertions), pint clean, npm run types:check clean.
<!-- SECTION:FINAL_SUMMARY:END -->
