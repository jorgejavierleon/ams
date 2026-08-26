---
id: KOL-89
title: Redesign the payroll report filter UI
status: Done
assignee:
  - jorgejavierleon@gmail.com
created_date: '2026-08-26 10:25'
updated_date: '2026-08-26 10:56'
labels:
  - frontend
  - payroll-reports
milestone: m-0
dependencies:
  - KOL-19
ordinal: 67000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The shared report filter (KOL-19: report-filter-form.tsx, employee-picker.tsx on the payroll-reports landing page) works but its UI is bare-bones default shadcn: a native `<input type=month>` plus a period-type dropdown, and DataTableFacetedFilter's stock popover. A UX mock (Claude Design project "Payroll report filter redesign", file `Reporte de remuneraciones - pantalla real.dc.html`) reshapes the same functionality into a more guided flow: a period card with prev/next month arrows and a quincena segmented control, richer facet popovers (search box, option counts, per-facet clear, explicit "Listo" close), removable filter chips, a selection status bar with distinct states for none/select-all/manual selection, and a dedicated banner for employees manually excluded from a "select all matching" selection.

This is a visual/interaction redesign only — the underlying selection model (EmployeeSelection: selectAll + excluded/included ids), the server-driven DataTable/useServerTable foundation, and the org-scoped filter options are unchanged from KOL-19 and must keep working exactly as before.

## User stories for manual testing (Gherkin)

Given the payroll reports page
When the user opens the "Sucursal" facet filter
Then a popover opens with a search box, a scrollable list of options with counts, a per-facet "Limpiar" action, and a "Listo" button that closes it

Given one or more facet filters have selected values
When the user looks at the filter row
Then a removable chip is shown for each selected value, and a "Limpiar filtros" action appears that clears every filter at once

Given the period selector
When the user clicks the previous/next month arrow
Then the displayed month changes by one and the period range preview updates to match

Given the employee picker in "select all matching filters" mode
When the user unchecks one matching employee
Then that employee moves into an "Excluidos a mano" banner with a "Volver a incluir" action, and clicking it re-includes the employee and removes them from the banner

Given the employee picker
When the user has zero, some, or all matching employees selected
Then the selection status bar text and accent visibly differ between "ningún trabajador seleccionado", a partial/manual selection, and "todos los que coinciden"
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The period selector uses prev/next month controls plus a quincena segmented control (mes completo / 1ra quincena / 2da quincena), not a native month input
- [x] #2 Each facet filter opens a popover with a search input, checkable options showing per-option counts, a per-facet Limpiar action, and a Listo button to close it
- [x] #3 Active filter values are shown as removable chips, with a Limpiar filtros action visible whenever at least one chip exists
- [x] #4 The selection status bar visibly distinguishes no selection, a manual selection, and select-all-matching
- [x] #5 In select-all mode, manually excluded employees are listed in a dismissible banner with a per-employee re-include action
- [x] #6 The table's selection column visually distinguishes included, excluded, and unselected rows
- [x] #7 All new labels are in Spanish
- [x] #8 The existing EmployeeSelection model, org-scoped filter options, and server-driven DataTable/useServerTable behaviour from KOL-19 are unchanged
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
1. Backend: ReportEmployeeSelector::optionsFor() adds a per-option `count` (org-scoped, filter-independent baseline, matching the mock's simple per-value tally) to premises/positions/costCenters/contractTypes via one groupBy(count) query per dimension against the unfiltered candidates() query. Extend ReportEmployeeSelectorTest with a test asserting counts.
2. Frontend types: add `ReportFacetOption = { value, label, count }` to payroll-reports/types.ts; PayrollReportFilterOptions uses it instead of the generic FacetedOption.
3. New payroll-reports/report-facet-filter.tsx: a payroll-reports-scoped (not touching the shared DataTableFacetedFilter used by employees/leaves/workdays) facet popover built from the same Command/Popover primitives, adding a per-option count, a per-facet "Limpiar" action, and an explicit "Listo" close button (controlled Popover open state).
4. New payroll-reports/period-selector.tsx: "Paso 1 - Periodo" panel with prev/next month arrow buttons (year-rollover aware), a computed period label + range caption, and a 3-button quincena segmented control (mes completo / 1ra / 2da), replacing the native <input type=month> + <Select>.
5. report-filter-form.tsx: swap in PeriodSelector; keep the resolved period math (resolvePeriodRange) as the single source for both the label and the range caption.
6. employee-picker.tsx:
   - toolbar uses ReportFacetFilter instead of DataTableFacetedFilter.
   - active filter values render as removable chips with a "Limpiar filtros" action once any chip exists.
   - selection status bar restyled with an accent bar + title/subtitle that differ across none/manual/select-all states.
   - in select-all mode with exclusions, a warning banner lists manually-excluded employees with a per-employee "Volver a incluir" action; employee names are cached client-side (from whatever page of `employees.data` has been seen) since exclusion always originates from clicking a currently-rendered row.
   - the row selection indicator becomes a small 3-state control (checked / excluded dashed-minus / plain empty) instead of the plain Checkbox, so excluded rows are visually distinct from merely-unselected ones.
7. Add the new Spanish + English keys under ui.payroll_reports.filters.* (period arrows, quincena short labels, facet clear/done, chip remove, status bar variants, excluded banner + undo).
8. vendor/bin/pint --dirty --format agent; sa test --compact --filter=ReportEmployeeSelector,PayrollReportSection while iterating; npm run types:check; final full sa test --compact before finalizing.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verification evidence:
- Backend: ReportEmployeeSelectorTest (new test) + full existing suite pass (sail artisan test --compact: 1216 tests, 1212 passed, 4 skipped, 0 failed).
- pint --dirty --format agent: clean. npm run types:check: clean. npm run lint: clean (0 errors, 1 pre-existing unrelated warning in use-server-table.ts).
- Live browser verification (chrome-devtools MCP, logged in as admin@example.com against localhost/payroll-reports):
  - Period card renders with prev/next month buttons and a 3-way quincena segmented control instead of the old <input type=month>/<Select>; clicking the arrows and quincena buttons updated the label and range caption.
  - Facet popover (Sucursal) opened with a search box, per-option counts (8/6), a "Limpiar" action, and a "Listo" close button; selecting an option round-tripped through the server (URL gained premises[0]=1, match count updated 15 -> 8).
  - A removable chip ("Sucursal: Sucursal Centro x") appeared with a "Limpiar filtros" action once a filter was active.
  - Selection status bar text/subtitle verified in all three states: none ("Ningun trabajador seleccionado"), manual ("1 seleccionados uno por uno"), and select-all ("Todos los que coinciden con los filtros").
  - Excluding a row in select-all mode surfaced the "Excluidos a mano (1)" banner with the employee's name and a "Volver a incluir" action; clicking it removed the banner and re-included the row.
  - Inspected the row selection indicator's class list directly: checked rows carry bg-primary, the excluded row carries border-dashed border-amber-600, confirming three visually distinct states (AC #6).
- Only the payroll-reports-scoped report-facet-filter.tsx was added; the shared DataTableFacetedFilter (used by employees/leaves/workdays) was left untouched.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Redesigned the KOL-19 payroll-report filter UI to match the Claude Design mock (Reporte de remuneraciones - pantalla real): a Paso 1/Paso 2 layout with a period card (prev/next month arrows + quincena segmented control), richer facet popovers with per-option counts and a Listo/Limpiar action, removable filter chips, a three-state selection status bar (none/manual/select-all), an "excluidos a mano" banner with per-employee undo, and a three-state row selection indicator (checked/excluded/plain). Added a small backend addition (per-option employee counts in ReportEmployeeSelector::optionsFor()) to support the facet counts. The underlying EmployeeSelection model, org scoping, and DataTable/useServerTable foundation from KOL-19 are unchanged. Verified with a new Pest test plus the full suite (1212 passed, 4 skipped), pint/types-check/eslint clean, and a live browser walkthrough of every interaction (facet popover, chips, all three selection states, exclude/undo, and the three row-indicator styles).
<!-- SECTION:FINAL_SUMMARY:END -->
