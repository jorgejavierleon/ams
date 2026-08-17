---
id: KOL-75
title: Replace Jornadas row-action icons with a three-dot dropdown menu
status: To Do
assignee: []
created_date: '2026-08-17 20:02'
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
- [ ] #1 The Jornadas index actions column shows a single three-dot trigger per row instead of separate icon buttons
- [ ] #2 Opening the dropdown lists every action available for that row (view, modify marks, and — when applicable — approve/object overtime), each item showing both an icon and its label
- [ ] #3 An action only appears in the menu when the viewer is actually permitted to take it, matching today's per-icon conditional visibility (e.g. overtime approve/object only for a row with can_decide true)
- [ ] #4 Destructive or negative actions (objecting to overtime) are visually distinguished within the menu, consistent with how resources/js/pages/leaves/index.tsx styles its own negative actions
- [ ] #5 All labels are in Spanish and the existing aria-labels/keyboard accessibility of the current icon buttons are preserved or improved, not lost
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
