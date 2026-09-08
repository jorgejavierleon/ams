---
id: KOL-113
title: Fix the DataTable toolbar/filter row layout on narrow viewports
status: To Do
assignee: []
created_date: '2026-09-06 20:41'
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
- [ ] #1 The DataTable toolbar row (resources/js/components/data-table.tsx) stacks the search input and the toolbar filters into a clean, legible layout on narrow viewports instead of the current overlapping/misaligned wrap
- [ ] #2 The fix lives in the shared DataTable component so every page passing both searchPlaceholder and toolbar benefits (Employees, Documents, My Leaves, Leaves, Workdays, SaaS Audit Log, Payroll Reports History)
- [ ] #3 Verified visually on the Employees list (the page with the most filters) at a tablet width (~768px) and a phone width (~390px)
- [ ] #4 No regression to the existing desktop (wide viewport) layout on Employees and at least one other DataTable+toolbar page
- [ ] #5 The column-visibility (Columnas) control stays reachable and usable at narrow widths
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
