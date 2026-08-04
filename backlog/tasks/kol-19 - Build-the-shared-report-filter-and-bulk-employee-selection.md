---
id: KOL-19
title: Build the shared report filter and bulk employee selection
status: To Do
assignee: []
created_date: '2026-08-04 11:13'
updated_date: '2026-08-04 19:00'
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
- [ ] #1 Reports can be filtered by company, premise, cost centre, position, contract type, individual employee and date range
- [ ] #2 The period selector supports a month and a quincena, not only an arbitrary date range
- [ ] #3 A user can select all employees matching the filters and then exclude specific ones, and the exclusion survives changing the filters
- [ ] #4 The resolved selection is a single object consumed identically by the aggregation service, the integrity check and the audit record
- [ ] #5 The employee picker is backed by the existing useServerTable/DataTable foundation rather than a new bespoke table
- [ ] #6 Filters are organization-scoped: the dropdowns never offer another tenant's premises, cost centres or employees
- [ ] #7 Selecting no employees is handled explicitly rather than silently exporting the whole company
- [ ] #8 All labels are in Spanish
- [ ] #9 Pest tests cover each filter dimension, the exclusion behaviour across a filter change, the quincena period, and tenant scoping
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
