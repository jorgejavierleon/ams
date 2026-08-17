---
id: KOL-76
title: Add an overtime-status filter to the Jornadas index
status: Done
assignee: []
created_date: '2026-08-17 20:31'
updated_date: '2026-08-17 20:54'
labels:
  - frontend
  - overtime
dependencies: []
references:
  - resources/js/pages/workdays/index.tsx
ordinal: 54000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The Jornadas index (resources/js/pages/workdays/index.tsx, backed by WorkdayController) shows each row's overtime status as a badge (pending/approved/objected, or a dash when no overtime was calculated), but the toolbar has no way to filter by it. HR/supervisors reviewing overtime today have to scan every page by eye. Add a faceted filter (matching the existing status/employee/position/premise filters, using OvertimeAuthorizationStatus::options() for the pending/approved/objected values) so the table can be narrowed to rows in a specific overtime state, making it easier to find rows that still need a decision.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The Jornadas toolbar has a new overtime-status filter alongside the existing status/employee/position/premise filters
- [x] #2 Selecting pending, approved, or objected restricts the table to workdays whose overtime authorization has that status
- [x] #3 The filter is combinable with the existing filters and round-trips through the URL/query params like the others
- [x] #4 Filter option labels are in Spanish and reuse OvertimeAuthorizationStatus's existing labels
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
1. Backend: WorkdayController#index adds overtime_statuses filter param (enumListFilter over OvertimeAuthorizationStatus), whereHas('overtimeAuthorization') scope, passes filters.overtime_statuses + overtimeStatusOptions to Inertia.
2. Frontend: workdays/index.tsx adds overtimeStatuses state, extraParams entry, and a DataTableFacetedFilter in the toolbar using overtimeStatusOptions.
3. Lang: add ui.workdays.filters.overtime_status label (es).
4. Pest test in WorkdayManagementTest.php mirroring the existing status-filter test, using OvertimeAuthorization factory states.
5. pint --dirty, sa test --compact, npm run types:check.
<!-- SECTION:PLAN:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added an overtime-status faceted filter to the Jornadas toolbar (WorkdayController#index + workdays/index.tsx), reusing OvertimeAuthorizationStatus::options() for Spanish labels. Combinable with existing filters and round-trips via filters.overtime_statuses. Verified with new Pest tests (WorkdayManagementTest), full suite (1106 passed/4 skipped), pint clean, tsc clean.
<!-- SECTION:FINAL_SUMMARY:END -->
