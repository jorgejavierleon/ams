---
id: KOL-4.1
title: Add a Permisos (leave history) tab to the employee profile page
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-19 10:16'
updated_date: '2026-09-19 12:18'
labels: []
dependencies: []
references:
  - 'https://claude.ai/code/artifact/1a0b9a52-a6d2-4bb7-9833-08a503dbd97a'
parent_task_id: KOL-4
ordinal: 111000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Problem Statement

The employee profile page's Turnos tab shows shift assignments and overtime pacts, but there's no view of an employee's individual leave (permiso) history — an admin has to go to the separate Leaves section and filter by employee to see it. The approved redesign mockup for the parent task adds a dedicated "Permisos" tab for this. See parent task KOL-4 and mockup: https://claude.ai/code/artifact/1a0b9a52-a6d2-4bb7-9833-08a503dbd97a

## Solution

Add a "Permisos" tab (between Turnos and Documentos, matching the approved mockup) to `employees/show.tsx` that lists the employee's `Leave` records (type, date range, business days requested, status), most recent first, using the existing `App\Models\Leave` model (already used by the Leaves and my/leaves sections) scoped to this employee via `user_id`.

## User Stories

1. As an admin viewing an employee's profile, I want a Permisos tab listing their leave requests (type, dates, days, status), so I don't have to leave the page and filter the Leaves section by employee.
2. As an admin, I want to see the leave type and status using the same labels/terms used elsewhere in the app (Leaves list, my/leaves), so the vocabulary is consistent.
3. As an admin, I want the list ordered most-recent-first, so the employee's current/upcoming leave is visible without scrolling.
4. As an admin viewing an employee who has never requested leave, I want a clear empty state, so I don't mistake it for a loading or broken tab.

## User stories for manual testing (Gherkin)

Scenario: An employee's leave history renders in the Permisos tab
  Given an employee "Camila Torres" has an approved "Vacaciones" leave from 2025-01-02 to 2025-01-09
  And a pending "Vacaciones" leave from 2026-12-20 to 2026-12-27
  When I open her employee detail page and select the "Permisos" tab
  Then I see both leaves listed with their type, date range, business days requested, and status
  And the pending 2026 leave appears above the approved 2025 leave

Scenario: An employee with no leave history shows an empty state
  Given an employee "New Hire" has no Leave records
  When I open his employee detail page and select the "Permisos" tab
  Then I see an empty-state message instead of an empty table

Scenario: Leaves from other employees never appear
  Given "Camila Torres" and "Roberto Fernández" each have their own leave records
  When I open Camila's employee detail page and select the "Permisos" tab
  Then I only see Camila's leave records, never Roberto's

## Implementation Decisions

- Follow the existing deferred-prop pattern already used for the Documentos tab so the Permisos tab's data loads lazily like Documentos does, rather than on every load of the Info/Laboral tabs.
- Query `Leave::where('user_id', $employee->id)`, ordered by `start_date` descending; reuse the `LeaveType` and `LeaveStatus` enum labels already used by the Leaves index/my-leaves pages for consistent terminology.
- Match the mockup's table columns: Tipo, Desde, Hasta, Días, Estado — reuse existing status-badge styling conventions (success/warning/danger) already used elsewhere in the app for leave status.

## Testing Decisions

- Add a Pest feature test asserting the employee show page's Permisos data is scoped to the requested employee and excludes other employees' leaves.
- Cover the empty-state case with a Pest test or manual check.
- Manual browser check for ordering and status-badge rendering.

## Out of Scope

- Approving/rejecting/creating leave requests from this tab — read-only history view only, matching the mockup.
- Pagination — out of scope unless an employee has enough leave history to make the list unusably long (flag if discovered during implementation, per standing scope-change process).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Permisos tab appears between Turnos and Documentos on the employee profile page
- [x] #2 Tab lists the employee's own Leave records only (type, date range, business days requested, status), most recent first
- [x] #3 Leave type and status use the app's existing translated labels, consistent with the Leaves/my-leaves sections
- [x] #4 An employee with no leave records shows an empty state instead of an empty table
- [x] #5 A Pest test confirms leave records are scoped to the requested employee and never leak another employee's leaves
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
1. Backend: EmployeeController::show() adds a deferred 'leaves' prop (Inertia::defer), fetched via a new private employeeLeaves(User) method: Leave::where('user_id', employee->id)->orderByDesc('start_date')->get()->map(...) to {id, type:{value,label}, start_date, end_date (Y-m-d), business_days_requested, status:{value,label,badge}} using LeaveType/LeaveStatus enum label()/badge().
2. Frontend: new resources/js/components/employee-leaves.tsx (read-only table, modeled on employee-overtime-pacts.tsx) with columns Tipo/Desde/Hasta/Dias/Estado, badge tone map (success/warning/destructive -> Badge variant, same pattern as workdays/index.tsx STATUS_BADGE), and an empty-state message.
3. Wire into employees/show.tsx: add 'Permisos' TabsTrigger between Turnos and Documentos, TabsContent wraps <Deferred data='leaves'> + skeleton fallback (same shape as the Documentos tab) rendering <EmployeeLeaves>.
4. Add en/es lang/ui.php keys under ui.employees.show: tab_leaves, and a leaves sub-namespace (title, empty, columns.type/start_date/end_date/days/status) -- new column labels matching the approved mockup (Desde/Hasta), not reusing ui.leaves.columns which say Inicio/Fin elsewhere.
5. Pest test in tests/Feature/EmployeeManagementTest.php: fetch the deferred 'leaves' prop via an X-Inertia-Partial-Data request (pattern from DocumentActivityTimelineTest::fetchActivities), assert scoping to the employee (excludes another employee's leave) and most-recent-first ordering; separate test/assertion for the empty-list case.
6. Run vendor/bin/pint --dirty --format agent, php artisan test --compact --filter=EmployeeManagementTest, and npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified in browser (Chrome DevTools MCP) against seeded employee tjacobi@example.com (id=6, /employees/6): Permisos tab sits between Turnos and Documentos, table shows 'Sin goce de sueldo' (Rechazado, red) and 'Vacaciones' (Aprobado, green) ordered newest-first by start_date, matching the approved mockup. Empty-state branch verified via the passing Pest test asserting props.leaves is an empty array for an employee with no Leave rows (component's leaves.length === 0 branch is a straight conditional, no seeded employee had zero leaves to click through). Code review (mattpocock-skills:code-review) flagged two pre-existing duplication patterns (STATUS_BADGE tone->variant map already duplicated 3x elsewhere; per-employee Leave queries split across employeeLeaves()/vacationBalance() like the existing shiftAssignments/overtimePacts methods) -- both match established codebase conventions already, left as-is rather than expanding scope with a cross-file refactor.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a read-only Permisos tab to the employee profile page: EmployeeController::show() now sends a deferred 'leaves' prop (Leave::where user_id, newest first, using LeaveType/LeaveStatus enum labels and badge tone), rendered by a new EmployeeLeaves table component wired between Turnos and Documentos. Verified with two new Pest tests (scoping/ordering via partial-reload deferred-prop fetch, and the empty-list case) plus a live browser check against seeded data confirming tab position, ordering, and badge styling. vendor/bin/pint clean; sail artisan test --filter=EmployeeManagementTest passes 66/66; npm run types:check shows only pre-existing unrelated errors in roles/index.tsx and roles/show.tsx.
<!-- SECTION:FINAL_SUMMARY:END -->
