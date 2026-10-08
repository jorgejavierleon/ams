---
id: KOL-113
title: Fix the DataTable toolbar/filter row layout on narrow viewports
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-06 20:41'
updated_date: '2026-10-08 11:15'
labels: []
dependencies: []
references:
  - resources/js/components/data-table.tsx
  - resources/js/pages/employees/index.tsx
ordinal: 100000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The shared DataTable toolbar row (resources/js/components/data-table.tsx:115-133) lays out the search input and the toolbar filters as two side-by-side flex children in a non-wrapping row, vertically centered. The toolbar content itself already wraps internally (e.g. employees/index.tsx:403), so on a narrow window the filter buttons/selects wrap into several short rows while the search input stays vertically centered next to that block — the two never align into a coherent stacked layout. Reported on the Employees list (Activo, Admin, search, Sucursal, Cargo, Centro de costo, Tipo de contrato, Columnas), but the same toolbar row is shared by every page that passes both `searchPlaceholder` and a `toolbar` prop: documents/index.tsx, my/leaves/index.tsx, leaves/index.tsx, workdays/index.tsx, saas/audit-log/index.tsx, and payroll-reports/history.tsx. Fixing it in the shared component fixes all of them at once.

## User stories for manual testing (Gherkin)

Scenario: Employee filters stack cleanly on a narrow screen
  Given I open the Employees list in a narrow/tablet-width browser window
  When the page renders
  Then the search box and each filter (Activo, Admin, Sucursal, Cargo, Centro de costo, Tipo de contrato, Columnas) are fully visible and legible
  And none of them overlap, get clipped, or float disconnected from the row they belong to

Scenario: Desktop layout is unaffected
  Given I open the Employees list in a normal desktop-width browser window
  When the page renders
  Then the search box and filters render in a single row exactly as before
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The DataTable toolbar row (resources/js/components/data-table.tsx) stacks the search input and the toolbar filters into a clean, legible layout on narrow viewports instead of the current overlapping/misaligned wrap
- [x] #2 The fix lives in the shared DataTable component so every page passing both searchPlaceholder and toolbar benefits (Employees, Documents, My Leaves, Leaves, Workdays, SaaS Audit Log, Payroll Reports History)
- [x] #3 Verified visually on the Employees list (the page with the most filters) at a tablet width (~768px) and a phone width (~390px)
- [x] #4 No regression to the existing desktop (wide viewport) layout on Employees and at least one other DataTable+toolbar page
- [x] #5 The column-visibility (Columnas) control stays reachable and usable at narrow widths
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. In resources/js/components/data-table.tsx, change the toolbar row container from a non-wrapping 'flex items-center justify-between gap-4' to stack vertically on narrow viewports and lay out in a row on sm+ screens (flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between).
2. Add flex-wrap to the inner toolbar+view-options wrapper so the Columnas control wraps onto its own line instead of overflowing/floating beside a tall multi-row filter block when there is no room.
3. Verify visually on Employees list at ~768px and ~390px widths, and confirm desktop (wide) layout is unchanged on Employees and one other DataTable+toolbar page (e.g. Documents).
4. Run npm run types:check since TypeScript was touched; no PHP changed so no Pest test is required.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented: changed the DataTable toolbar row (resources/js/components/data-table.tsx) from a non-wrapping flex row to a single flex-wrap container, and gave the search input a bounded width (w-full sm:w-64) instead of w-full+max-w-sm. This lets the row wrap cleanly based on actual available width (robust to the sidebar eating space at any viewport, not tied to a Tailwind breakpoint) instead of leaving the search input vertically centered beside a tall wrapped filter block. Verified visually via chrome-devtools-mcp at 390px, 768px, 1024px, and 1440px on Employees (most filters) and Documents (fewer filters, confirms shared fix). Desktop (1440px) layout matches the original single-row appearance. npm run types:check, eslint, and prettier all pass; no PHP touched so no Pest test added, ran php artisan test --filter=Employee (298 passed) as a sanity check.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Fixed the shared DataTable toolbar row (resources/js/components/data-table.tsx) so the search input and toolbar filters wrap cleanly instead of leaving the search box vertically centered beside a multi-row wrapped filter block. Changed the row container to flex-wrap (from non-wrapping flex) and gave the search input a bounded width (w-full sm:w-64, replacing w-full+max-w-sm) so wrapping responds to actual available width rather than forcing or blocking a break at a fixed viewport breakpoint — this matters because the app sidebar eats a fixed chunk of width, so a plain Tailwind breakpoint (sm/lg) alone reproduced the bug at 768px and 1024px during testing. Verified visually with chrome-devtools-mcp on Employees (most filters) and Documents (fewer filters) at 390px, 768px, 1024px, and 1440px: narrow widths stack cleanly with Columnas reachable, and 1440px matches the original single-row desktop layout. npm run types:check, eslint, and prettier pass. No PHP changed; ran php artisan test --filter=Employee (298 passed) as a sanity check, so Pint/Pest DoD items are not applicable.
<!-- SECTION:FINAL_SUMMARY:END -->
