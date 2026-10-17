---
id: KOL-156
title: Replace remaining hasRole/role() name checks with permission checks
status: To Do
assignee: []
created_date: '2026-10-17 19:52'
labels:
  - tech-debt
  - authorization
dependencies: []
references:
  - KOL-133
priority: medium
ordinal: 182000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
KOL-133 and its follow-ups moved authorization from role names to Spatie permissions, but several `hasRole('admin')` / `->role('admin')` checks remain. Because the admin role is editable and the Owner may hold no admin role, a name check silently diverges from what an organization configured in the Roles screen. The settings/* routes were the last route-level case (found when the Owner got a 403 on Configuración general); the sites below decide data scope or recipients and still gate on the role name.

Sites (verify with `grep -rnE "hasRole|hasAnyRole|->role\(" app`):
- Org-wide scope (`hasRole('admin') || isOwner()`): DashboardController (3x), LeaveController, LeaveCalendarController, OvertimeRequestController, HandleInertiaRequests, Mcp ViewTeamLeavesTool, Mcp ViewTeamOvertimeRequestsTool
- Approver/recipient lookup: LeaveApprovers, OvertimeRequestApprovers (`->role('admin')`)
- Settings access: Settings/OrganizationController (`isOwner() || hasRole('admin')`)
- Record-type checks: ShiftAssignmentController and EmployeeController (`hasRole('employee')`) — decide whether these identify "is an employee record" and may stay.
Out of scope: `role:saas` (separate guard, super-admin panel).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Each org-wide scope check uses an existing permission (e.g. Manage:OvertimeAuthorization, Create:Leave) or a new one added to RoleSeeder::ADMIN_PERMISSIONS, with ui.php labels in es and en
- [ ] #2 Approver/recipient lookups select users by permission, not by the admin role name
- [ ] #3 An Owner with no admin role and an org that edits the admin role both get behavior matching their effective permissions
- [ ] #4 Remaining hasRole usages are limited to deliberate cases (employee record type, saas), each with a short justification
- [ ] #5 Feature tests cover Owner-without-role, role-with-permission and role-without-permission for each changed site
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
