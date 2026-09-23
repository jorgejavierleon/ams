---
id: KOL-124
title: Show avatar + name in the employee column on all list tables
status: In Review
assignee:
  - jorgejavierleon@gmail.com
created_date: '2026-09-23 11:31'
updated_date: '2026-09-23 11:48'
labels: []
dependencies: []
ordinal: 124000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The Employees list renders its employee column as an avatar + name, linked to the employee's show page (resources/js/pages/employees/index.tsx). Every other list table that has an 'employee' column (Leaves, Workdays, Documents, DT Documents, Overtime Pacts, Overtime Rest-Day Balances, Overtime Requests) currently renders it as plain text with no avatar and no link. Bring those columns in line with the Employees list for a consistent UI, extracting a shared cell component so the pattern isn't duplicated across 7 pages.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A shared component renders an employee avatar (with initials fallback) + name, linking to the employee show page, reused by the Employees list and every other affected table
- [x] #2 Leaves, Workdays, Documents, DT Documents, Overtime Pacts, Overtime Rest-Day Balances, and Overtime Requests index tables show the employee avatar + name in their employee column
- [x] #3 Each affected controller passes the employee id and avatar url alongside the existing name in its Inertia payload
- [x] #4 Employee avatar/name in these tables links to the employee show page for users who can view it, and degrades gracefully (no link) for users without permission
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add a shared EmployeeCell component (avatar + name, optional href) in resources/js/components/employee-cell.tsx, matching the Employees list's existing cell.
2. Add auth.isAdmin to the shared Inertia props (HandleInertiaRequests) since employees.* routes are gated by the admin role, not a permission string; expose it on the frontend Auth type.
3. Refactor employees/index.tsx to use EmployeeCell.
4. For each of Leaves, Workdays, Documents, DT Documents, Overtime Pacts, Overtime Rest-Day Balances, Overtime Requests: add employee_id/employee_avatar (or user_id/employee_avatar where an id already existed) to the controller's Inertia payload, eager-load the user's media relation to avoid N+1, and render EmployeeCell in the employee column, linking to the employee show page only when auth.isAdmin is true (DT Documents links to the document instead, matching its existing behavior).
5. Verify with Pest feature tests (existing index tests extended with employee_id/employee_avatar assertions) and a manual pass through each page in the browser.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Ran targeted Pest suites only (LeaveManagementTest, DocumentManagementTest, DtDocumentsTest, OvertimePactManagementTest, OvertimeRestDayBalanceControllerTest, OvertimeRequestReviewTest, WorkdayManagementTest, LayoutTest, SupervisorLeaveApprovalTest) — 120/120 passing. Full suite intentionally not run without user request (standing preference). Manually verified Leaves, Workdays, Documents, and Employees index pages in a browser with live seeded data: avatar + name render correctly and the employee link resolves to /employees/{id} for the admin session; no console errors. Overtime Pacts/Requests/Rest-day-balances had no seeded rows to eyeball but are covered by Pest assertions on employee_id/employee_avatar.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Extracted a shared EmployeeCell (avatar + name, optional href) component and reused it on the Employees list plus the employee column of Leaves, Workdays, Documents, DT Documents, Overtime Pacts, Overtime Rest-Day Balances, and Overtime Requests. Each controller now sends employee_id/user_id + employee_avatar (with user.media eager-loaded to avoid N+1). Added a shared auth.isAdmin Inertia prop (employees.* is admin-role-gated, not permission-gated) so the cell only links to the employee's show page for admins; DT Documents keeps its existing link to the document itself. Verified via Pest (pint clean, targeted tests green, tsc clean) and a manual browser pass.
<!-- SECTION:FINAL_SUMMARY:END -->
