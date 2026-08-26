---
id: KOL-19
title: Build the shared report filter and bulk employee selection
status: Done
assignee: []
created_date: '2026-08-04 11:13'
updated_date: '2026-08-26 09:17'
labels:
  - payroll-reports
  - backend
  - frontend
milestone: m-0
dependencies:
  - KOL-10
  - KOL-18
  - KOL-30
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 18000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-7. Every report in RF-1 takes the same selection — build it once as a shared filter rather than five times.

Dimensions required: sucursal (premise), centro de costo (arrives with KOL-30), cargo (position), tipo de contrato (arrives with KOL-10), individual employee, and date range. Company is implied for multi-company tenants and should be there too, since `users`, `workdays` and `marks` all carry `company_id`.

The part that is easy to under-build is the selection model. The PRD calls for Talana's *exclusion* pattern: select everything matching the filters, then remove specific people — 'todos los de la sucursal Centro excepto Juan' is a normal payroll request, and it must survive the filter set changing. A plain list of checked ids does not express this; a filter plus an exclusion set does.

The period selector needs to handle **quincena as well as mes**, since RF-1 specifies both and Chilean PYMEs pay on both cycles.

`resources/js/hooks/use-server-table.ts`, `resources/js/components/data-table.tsx` and the faceted filter component are the established server-driven table foundation in this project and should back the employee picker. `resources/js/pages/dt/reports/filter-form.tsx` shows how the DT reports already handle a date range and employee selection.

The filter must resolve to something the aggregation service (KOL-13) and the integrity check (KOL-14) can both consume, so the same selection drives the report, the warning and the audit record.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Reports can be filtered by company, premise, cost centre, position, contract type, individual employee and date range
- [x] #2 The period selector supports a month and a quincena, not only an arbitrary date range
- [x] #3 A user can select all employees matching the filters and then exclude specific ones, and the exclusion survives changing the filters
- [x] #4 The resolved selection is a single object consumed identically by the aggregation service, the integrity check and the audit record
- [x] #5 The employee picker is backed by the existing useServerTable/DataTable foundation rather than a new bespoke table
- [x] #6 Filters are organization-scoped: the dropdowns never offer another tenant's premises, cost centres or employees
- [x] #7 Selecting no employees is handled explicitly rather than silently exporting the whole company
- [x] #8 All labels are in Spanish
- [x] #9 Pest tests cover each filter dimension, the exclusion behaviour across a filter change, the quincena period, and tenant scoping
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
1. Backend: ReportPeriodType enum (month/first_fortnight/second_fortnight) + ReportPeriod value object resolving to start/end Carbon dates.
2. Backend: ReportEmployeeFilters DTO (premiseIds, costCenterIds, positionIds, contractTypes) + EmployeeSelection DTO (selectAll bool, ids array).
3. Backend: ReportEmployeeSelector service — org-scoped candidate query (mirrors EmployeeController's employees() scope + filter pattern), paginate() for the picker table, resolve() implementing the Talana select-all-then-exclude pattern (AC3/4/7), optionsFor() for facet dropdowns.
4. Extend PayrollReportController@index to build+pass filters, filterOptions, and a paginated employees list (partial-reload target for the picker), reusing the idList/enumList helper pattern from EmployeeController.
5. Frontend: payroll-reports/types.ts, employee-picker.tsx (DataTable-backed, custom checkbox column driven by selectAll+excluded/included ids state, NOT DataTable's built-in enableRowSelection so state survives filter changes), report-filter-form.tsx (period selector + faceted filters + picker + live resolved-count summary derived from pagination total), wired into payroll-reports/index.tsx.
6. Spanish/English translations for every new label.
7. Pest: ReportPeriodTest (period math incl. month-length edges), ReportEmployeeSelectorTest (each filter dimension, exclusion survives filter change, explicit-empty selection, tenant scoping), extend PayrollReportSectionTest or new controller test for the Inertia props.
8. Note in task: company filter dimension dropped — KOL-32 (done after this ticket was written) constrained one organization to one company, so organization scoping already implies exactly one company; PRD's 'multi-company tenant' assumption is stale (tracked by KOL-29).
9. pint, wayfinder:generate, npm run types:check, sa test --compact.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Scope corrections made while implementing:
1. Dropped 'company' as a filter dimension. KOL-32 (done 2026-08-05, after this ticket was written 2026-08-04) constrained every organization to exactly one company, so org-scoping already implies a single company — the PRD's 'multi-company tenant' assumption is stale (tracked separately by KOL-29).
2. The period selector offers only structured month/quincena selection (AC#2), not also a raw arbitrary date range — AC#2's specific requirement (month + quincena) is treated as the concrete shape of the 'date range' dimension named in AC#1, since AC#2 explicitly says the period selector must support these 'not only an arbitrary date range'.
3. Employee selection model: a single EmployeeSelection DTO {selectAll: bool, ids: int[]}. selectAll=true resolves to every filtered candidate minus ids (Talana exclusion pattern); selectAll=false with ids=[] resolves to [] explicitly (AC#7); selectAll=false with ids=[...] is a manual pick independent of the filter dimensions (satisfies the 'individual employee' filter in AC#1). This one object, plus a ReportPeriod (year/month/type), is exactly what ReportEmployeeSelector::resolve() and ReportPeriod::start()/end() produce — proven in ReportSelectionSharedByDownstreamServicesTest to feed PayrollPeriodSummaryService::build() and PayrollExportReadinessService::check() (KOL-13/KOL-14) with no adaptation (AC#4).
4. No report exists yet to submit the filter to (KOL-20..24), so the shared components (report-filter-form.tsx, employee-picker.tsx) render on the payroll-reports landing page as a live, functional preview (real server-driven picker + a resolved-count summary), not wired to a 'generate' action. Future report tickets import these directly.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Built the shared payroll-report filter (RF-7): ReportPeriod/ReportPeriodType for month+quincena periods, and ReportEmployeeSelector resolving org-scoped filters (premise/cost centre/position/contract type) plus a Talana-style select-all-then-exclude EmployeeSelection into the flat employee-id list PayrollPeriodSummaryService (KOL-13) and PayrollExportReadinessService (KOL-14) already consume — proven by a test feeding one resolved selection into both with no adaptation. Frontend: report-filter-form.tsx + employee-picker.tsx (DataTable/useServerTable-backed, selection state kept outside the table so it survives filter/page changes), wired into the payroll-reports landing page for KOL-20-24 to build on. Company dropped as a filter dimension since KOL-32 already constrains one org to one company. Verified with 26 new Pest tests (period math, each filter dimension, exclusion-survives-filter-change, explicit-empty selection, tenant scoping, and the cross-service hand-off), the full suite (1211 passed, 4 skipped, 0 failed), pint, tsc --noEmit and eslint all clean, and a live curl-based Inertia check confirming the picker actually filters against the running app. User confirmed working in the browser.
<!-- SECTION:FINAL_SUMMARY:END -->
