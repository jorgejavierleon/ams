---
id: KOL-76
title: Add an overtime-status filter to the Jornadas index
status: To Do
assignee: []
created_date: '2026-08-17 20:31'
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
- [ ] #1 The Jornadas toolbar has a new overtime-status filter alongside the existing status/employee/position/premise filters
- [ ] #2 Selecting pending, approved, or objected restricts the table to workdays whose overtime authorization has that status
- [ ] #3 The filter is combinable with the existing filters and round-trips through the URL/query params like the others
- [ ] #4 Filter option labels are in Spanish and reuse OvertimeAuthorizationStatus's existing labels
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
