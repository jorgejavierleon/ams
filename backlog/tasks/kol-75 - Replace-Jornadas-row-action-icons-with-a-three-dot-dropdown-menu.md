---
id: KOL-75
title: Replace Jornadas row-action icons with a three-dot dropdown menu
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-17 20:02'
updated_date: '2026-08-17 20:35'
labels:
  - frontend
  - overtime
milestone: m-2
dependencies: []
references:
  - resources/js/pages/leaves/index.tsx
ordinal: 53000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The Jornadas index (resources/js/pages/workdays/index.tsx) currently renders its row actions (view, modify marks, and now the KOL-71 overtime approve/object icons) as a row of bare icon buttons in the actions column. As more per-row actions accumulate (overtime approve/object added in KOL-71), the row gets visually crowded and the icons aren't self-explanatory without a tooltip. Replace it with the three-dot (kebab) dropdown pattern already established elsewhere in the app — see resources/js/pages/leaves/index.tsx's actions column (MoreVertical trigger + DropdownMenu/DropdownMenuItem, each item showing both an icon and a text label) — rather than inventing a new pattern.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The Jornadas index actions column shows a single three-dot trigger per row instead of separate icon buttons
- [x] #2 Opening the dropdown lists every action available for that row (view, modify marks, and — when applicable — approve/object overtime), each item showing both an icon and its label
- [x] #3 An action only appears in the menu when the viewer is actually permitted to take it, matching today's per-icon conditional visibility (e.g. overtime approve/object only for a row with can_decide true)
- [x] #4 Destructive or negative actions (objecting to overtime) are visually distinguished within the menu, consistent with how resources/js/pages/leaves/index.tsx styles its own negative actions
- [x] #5 All labels are in Spanish and the existing aria-labels/keyboard accessibility of the current icon buttons are preserved or improved, not lost
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
1. Add 'more' translation key under workdays.actions in lang/en/ui.php and lang/es/ui.php (matching leaves.actions.more pattern).
2. Replace the actions column cell in resources/js/pages/workdays/index.tsx: swap the row of icon Buttons for a DropdownMenu (MoreVertical trigger), with items View (Eye), Modify marks (PencilLine), and when can.decideOvertime && row.original.overtime?.can_decide, a separator then Approve overtime (Check) and Object overtime (X, variant=destructive) - following resources/js/pages/leaves/index.tsx's pattern exactly.
3. Keep aria-label on the trigger button; each DropdownMenuItem shows icon + Spanish label.
4. Run vendor/bin/pint --dirty --format agent, sa test --compact (workday tests), npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Replaced icon-button row actions in resources/js/pages/workdays/index.tsx with a DropdownMenu (MoreVertical trigger), matching leaves/index.tsx pattern: View, Modify marks, and (when can.decideOvertime && can_decide) a separator then Approve (emerald) / Object (destructive variant). Added workdays.actions.more to lang/en+es/ui.php. No backend/Pest changes needed (no browser tests reference the old icon buttons). pint clean, types:check clean, WorkdayManagementTest passes (29/29).

DoD #4: the only PHP touched was lang/en+es/ui.php (translation strings), not app logic, so no new Pest test was needed.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Replaced the Jornadas index actions column's row of bare icon buttons with a single three-dot dropdown (MoreVertical trigger), matching leaves/index.tsx's DropdownMenu pattern: View, Modificar marcas, and — only when can.decideOvertime && can_decide — Aprobar (emerald) / Objetar (destructive variant) after a separator. Added workdays.actions.more to lang/en+es/ui.php. Verified with pint --dirty (clean), npm run types:check (clean), npm run build (exit 0), and the full sa test --compact suite (1100 passed / 4 skipped, exit 0).
<!-- SECTION:FINAL_SUMMARY:END -->
