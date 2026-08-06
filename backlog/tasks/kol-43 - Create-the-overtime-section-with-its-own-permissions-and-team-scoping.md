---
id: KOL-43
title: Create the overtime section with its own permissions and team scoping
status: To Do
assignee: []
created_date: '2026-08-06 02:52'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-11
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 850
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 6 defines four roles with different authority over overtime, and every UI task in this milestone needs those permissions to already exist. This task creates the section shell and the permission vocabulary once, so the approval queue, the pactos screen and the employee request flow each just consume them.

The authority split from the PRD:
- **Employee** — requests overtime when the tenant runs Mode A, and sees their own history and status. Nothing else.
- **Supervisor** — approves or objects to their own team requests and shift excesses. Explicitly *cannot* modify legal-cap configuration.
- **HR Admin** — configures tenant policy, reviews anomalies, manages pactos, generates the payroll export.

Follow the project convention exactly: application code gates on Spatie **permissions**, never on role names, and the seeder is what assigns permissions to roles. `database/seeders/RoleSeeder.php` already shows the pattern with its `EMPLOYEE_PERMISSIONS` / `SUPERVISOR_PERMISSIONS` / `ADMIN_PERMISSIONS` constants and the `Own` / `Team` / `Any` naming (`RequestOwn:Leave`, `ViewTeam:Leave`, `ApproveTeam:Leave`). Team scoping itself lives in the policy, not the permission — `app/Policies/LeavePolicy.php` checks `$leave->user->supervisor_id === $user->id` alongside the permission, and the overtime policy should do the same.

The section itself is a route group and a nav entry in Spanish, following the shape of the leaves section in `routes/web.php`. Admins reach everything through the existing super-admin gate; supervisors reach their team through the granted permissions.

Nothing user-facing beyond the empty section lands here — the screens arrive with their own tasks. What lands is that a supervisor and an HR admin have distinguishable, revocable authority over overtime from the Roles screen on day one, rather than that being retrofitted after three screens have already hardcoded a check.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Distinct permissions exist for requesting own overtime, viewing own overtime, viewing and deciding a team overtime, and administering overtime policy and pactos
- [ ] #2 The seeder grants the request and view-own permissions to the employee role and the team decision permissions to the supervisor role, and an admin can revoke either from the Roles screen
- [ ] #3 A supervisor can only decide overtime for their own direct reports; the team constraint is enforced in the policy, not encoded in the permission name
- [ ] #4 A supervisor cannot modify legal-cap or tenant policy configuration, per PRD section 6
- [ ] #5 The overtime section appears in the navigation in Spanish only for users holding a permission that grants access to it
- [ ] #6 Application code gates on permissions rather than role names throughout
- [ ] #7 Pest tests cover an employee, a supervisor deciding for their own report, a supervisor refused for someone else report, an admin, and a user with no overtime permission at all
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
