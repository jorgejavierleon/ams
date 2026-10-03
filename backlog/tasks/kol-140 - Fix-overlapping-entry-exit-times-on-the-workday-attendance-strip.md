---
id: KOL-140
title: Fix overlapping entry/exit times on the workday attendance strip
status: Done
assignee:
  - '@Jorge Leon'
created_date: '2026-10-03 19:31'
updated_date: '2026-10-03 23:37'
labels: []
dependencies: []
ordinal: 155000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
On the workday detail page (/workdays/{id}), the attendance strip (AttendanceStrip in resources/js/components/workday-detail.tsx) plots the actual mark time + delta badge above the shift-window rail, positioned by percentage along the timeline. When the actual mark time is close to the scheduled shift start/end, the mark's time/delta block visually overlaps or collides with the 'Entrada turno'/'Salida turno' scheduled-time labels at the top of the strip, making both hard to read. Move the actual entry/exit time (and its +/- delta) to sit above the rail, next to the 'Entrada turno'/'Salida turno' labels, instead of its current position which can overlap the gray baseline area.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 When the actual mark time is close to the scheduled shift start or end, the actual time/delta no longer visually overlaps the 'Entrada turno'/'Salida turno' labels or the gray shift-window bar
- [x] #2 The actual entrance and exit times (with their +/- delta) are displayed above the timeline rail, positioned near the 'Entrada turno'/'Salida turno' labels
- [x] #3 The colored dot marker for each mark remains on the rail at its correct proportional position along the timeline
- [x] #4 Layout is verified in the browser on a workday where entry/exit marks are close to scheduled times (e.g. the case shown in KOL ticket screenshot: entrada 14:02 vs turno 14:00, salida 21:40 vs turno 22:00)
- [x] #5 No regression on workdays where marks are far from scheduled times (large on-time, late, or early deltas)
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. In AttendanceStrip (resources/js/components/workday-detail.tsx), replace the per-mark dot+time+delta block (currently rendered at its own x position via pct(mark.at), which collides with the Entrada/Salida labels when actual time is near scheduled time) with two separate concerns:
   - Anchor label group (top-2, positioned at the SCHEDULED pct, i.e. pct(shiftStart)/pct(shiftEnd)): stacks the existing 'Entrada turno'/'Salida turno' label with the actual mark's time + delta directly beneath it, so both texts are always locked together regardless of how close the actual mark is to the scheduled time.
   - Dot marker (unchanged vertical position ~top-34, on the rail): stays positioned at the ACTUAL mark's pct(mark.at), independent of the label group, satisfying AC#3.
2. Keep existing delta color logic (amber for late-in/early-out, emerald otherwise) and durationLabel formatting.
3. Verify in browser against the KOL screenshot case (entrada 14:02 vs turno 14:00, salida 21:40 vs turno 22:00) and a far-apart case (large delta) to confirm no regression.
4. Run npm run types:check. No PHP touched, so pint/sa test/Pest are not applicable for this change.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Redesigned AttendanceStrip's top layer: the actual mark time + delta is now anchored at the SCHEDULED pct (same x as the Entrada/Salida label, stacked directly beneath it in one locked group), while the colored dot marker stays independently positioned at the ACTUAL mark's pct on the rail. This guarantees the label and actual-time text never separate/overlap regardless of how close actual is to scheduled. Pushed rail/shift-bar/dot/ticks down (top-34->62, top-42->70, top-46->74, top-62->90) and grew the strip from h-24 to h-32 to give the (now up to 3-line) label group room without colliding with the dot zone; applied uniformly so relative spacing (dot vs rail vs ticks) is unchanged from before. Verified in browser via dev server (already running) logged in as admin@example.com (seed test credentials) against workday 133 (entrada 14:02/turno 14:00, salida 21:40/turno 22:00 - the exact KOL screenshot case): no overlap, dots correctly on rail. Also temporarily mutated workday 134's marks (06:10/19:45 vs turno 08:00/17:00) to check the far-apart case, confirmed no regression, then reverted the test data. DoD #1/#2/#4 (pint/sa test/Pest) not applicable - no PHP touched, pure TSX layout change. types:check and eslint/prettier pass.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Split AttendanceStrip's top label from the dot marker: the actual mark time + delta is now anchored at the scheduled slot's x position (stacked under the Entrada/Salida label), while the colored dot keeps its own x at the actual mark's position on the rail. Verified in browser against the exact KOL screenshot case (14:02/14:00, 21:40/22:00) and a far-apart case - no overlap in either.
<!-- SECTION:FINAL_SUMMARY:END -->
