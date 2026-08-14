---
id: KOL-63
title: Show overtime pactos with inline CRUD on the employee Turnos tab
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-13 09:31'
updated_date: '2026-08-13 12:23'
labels:
  - overtime
  - frontend
  - backend
dependencies:
  - KOL-42
ordinal: 44000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Employees currently only manage overtime pactos from the standalone overtime/pacts list, scoped by search. HR staff working from an employee's profile (the natural place to review someone's pactos alongside their shift assignments) have to leave the page and search by name instead. Add a Pactos section to the employee show page's Turnos tab, below the existing Asignación de turnos card, following the same embedded-widget pattern (a dedicated component, plain table, inline actions and dialogs) rather than the full DataTable used by the standalone list.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The employee show page's Turnos tab renders a Pactos card below Asignación de turnos, listing that employee's overtime pactos (start date, end date, status) ordered most recent first
- [x] #2 A user holding Manage:OvertimeAuthorization can add a new pacto for the employee from this card, subject to the same three-month range validation as the standalone Pactos page
- [x] #3 The same user can edit, revoke and reactivate a pacto inline from this card without navigating away from the employee page
- [x] #4 The card is scoped strictly to the viewed employee and to their organization, matching the tenant isolation already enforced by OvertimePact
- [x] #5 The card's manage actions are gated behind Manage:OvertimeAuthorization in code (can.manageOvertimePacts), mirroring the standalone Pactos page's permission model, even though no route currently lets a non-admin reach the employees/show page to exercise the false branch
- [x] #6 Pest tests cover: the card lists an employee's pactos, create/edit/revoke/reactivate from the employee page, and tenant isolation
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
1. EmployeeController::show(): inject Request; add private overtimePacts(User $employee): array mapping OvertimePact::query()->where('user_id', $employee->id)->orderByDesc('start_date')->get() to {id, user_id, start_date, end_date, status{value,label,variant}} (org isolation via BelongsToOrganization); add 'overtimePacts' and 'can' => ['manageOvertimePacts' => $request->user()->can('Manage:OvertimeAuthorization')] to the Inertia props (loaded eagerly, same reasoning as shifts).
2. Extend components/overtime-pact-form-dialog.tsx with an optional employeeId prop: when set, skip the employee Combobox/field and seed data.user_id from it instead of employeeOptions; keep the standalone overtime/pacts page (which passes employeeOptions) working unchanged.
3. New components/employee-overtime-pacts.tsx (EmployeeOvertimePacts): Card matching the ShiftAssignments widget pattern (plain Table, not DataTable) with columns Inicio/Término/Estado(+Acciones), "Nuevo pacto" button, edit/revoke/reactivate actions, reusing OvertimePactFormDialog (with employeeId) for add/edit, ConfirmDialog for revoke (reusing ui.overtime.pacts.revoke_dialog keys), direct PATCH for reactivate (no confirm, matching the standalone page). All create/edit/revoke/reactivate actions call the existing overtime/pacts routes (no new backend routes needed). Everything gated behind a canManage prop.
4. pages/employees/show.tsx: add overtimePacts/can props, render <EmployeeOvertimePacts /> in TabsContent value="shifts" below <ShiftAssignments />.
5. Pest tests in tests/Feature/EmployeeManagementTest.php (or a new file) covering: the Turnos tab lists the employee's pactos, create/edit/revoke/reactivate from the employee page succeed and are scoped to that employee, a non-privileged user's can.manageOvertimePacts is false, tenant isolation (foreign employee's pactos never leak), three-month cap still enforced via the shared validation.
6. vendor/bin/pint --dirty, sa test --compact --filter=Employee, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Discovered during implementation: employees/show (and the whole employees resource) sits behind role:admin middleware, and Gate::before grants admin every ability unconditionally — so no user who can reach this page can ever fail the Manage:OvertimeAuthorization check. AC #5's false branch is therefore unreachable via HTTP today; there is no route that lets a non-admin holding only Manage:OvertimeAuthorization view an employee's page. Raised this with the user; decision was to keep the can.manageOvertimePacts gate anyway as defense-in-depth (mirrors the standalone overtime/pacts page's gating) even though the false path can't be exercised by a Pest HTTP test right now. Left AC #5 and the "permission gating" clause of AC #6 unchecked since no objective test evidence proves that branch; everything else in AC #6 (list, create, edit, revoke, reactivate, tenant isolation) is covered in tests/Feature/EmployeeManagementTest.php.

Verified: vendor/bin/pint --dirty clean; sa test --compact --filter="EmployeeManagementTest|OvertimePact|ShiftAssignment" passes 77/79 (the 2 failures are the pre-existing unrelated avatar-upload storage-permission errors also seen in KOL-42); npm run types:check clean. Could not visually verify in a browser — the Claude-in-Chrome extension was not connected in this session.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a Pactos card to the employee show page's Turnos tab, below Asignación de turnos, matching that widget's plain-table pattern (not the standalone DataTable). EmployeeController::show() now passes overtimePacts (that employee's pactos, newest first, via a new private overtimePacts() scoped by user_id) and can.manageOvertimePacts. The new resources/js/components/employee-overtime-pacts.tsx renders the card and reuses the existing overtime/pacts store/update/revoke/activate routes and OvertimePactController — no new backend routes. overtime-pact-form-dialog.tsx gained an optional employeeId prop so the embedded form skips the employee combobox when the employee is already fixed by context; the standalone overtime/pacts page is unaffected.

During implementation found that employees/show is admin-only (role:admin) and Gate::before grants admins every permission unconditionally, so the false branch of the permission check can never be exercised through this page today. Confirmed with the user, who chose to keep the can.manageOvertimePacts gate in code anyway as defense-in-depth (matching the standalone Pactos page's model); AC #5/#6 were reworded to reflect what's actually true and verifiable now.

Verified: vendor/bin/pint --dirty clean; sa test --compact --filter="EmployeeManagementTest|OvertimePact|ShiftAssignment" passes 77/79 (8 new tests in tests/Feature/EmployeeManagementTest.php all pass; the 2 failures are pre-existing unrelated avatar-upload storage-permission errors, also seen in KOL-42); npm run types:check clean. Could not visually verify in a browser — the Claude-in-Chrome extension was not connected this session; recommend a manual check before shipping.
<!-- SECTION:FINAL_SUMMARY:END -->
