---
id: KOL-43
title: Create the overtime section with its own permissions and team scoping
status: Done
assignee: []
created_date: '2026-08-06 02:52'
updated_date: '2026-08-11 13:34'
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
- [x] #1 Distinct permissions exist for requesting own overtime, viewing own overtime, viewing and deciding a team overtime, and administering overtime policy and pactos
- [x] #2 The seeder grants the request and view-own permissions to the employee role and the team decision permissions to the supervisor role, and an admin can revoke either from the Roles screen
- [x] #3 A supervisor can only decide overtime for their own direct reports; the team constraint is enforced in the policy, not encoded in the permission name
- [x] #4 A supervisor cannot modify legal-cap or tenant policy configuration, per PRD section 6
- [x] #5 The overtime section appears in the navigation in Spanish only for users holding a permission that grants access to it
- [x] #6 Application code gates on permissions rather than role names throughout
- [x] #7 Pest tests cover an employee, a supervisor deciding for their own report, a supervisor refused for someone else report, an admin, and a user with no overtime permission at all
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
1. RoleSeeder.php: add RequestOwn:OvertimeAuthorization/ViewOwn:OvertimeAuthorization to EMPLOYEE_PERMISSIONS, ViewTeam:OvertimeAuthorization/ApproveTeam:OvertimeAuthorization to SUPERVISOR_PERMISSIONS, Manage:Overtime to ADMIN_PERMISSIONS.
2. New app/Policies/OvertimeAuthorizationPolicy.php mirroring LeavePolicy's viewTeam/approve/canDecide shape, using OvertimeAuthorization::approve()/object() method names.
3. New route GET /overtime -> OvertimeController@index, name overtime.index, inside auth+verified group, middleware permission: OR of the 5 overtime permissions.
4. New app/Http/Controllers/OvertimeController.php with index() rendering overtime/index.
5. New resources/js/pages/overtime/index.tsx placeholder shell (Heading + description).
6. app-sidebar.tsx: add "Horas extra" nav item to employeeNavGroups (ViewOwn:OvertimeAuthorization) and adminNavGroups (ViewTeam/ApproveTeam/Manage:Overtime).
7. Translations in lang/es/ui.php + lang/en/ui.php: ui.nav.overtime, ui.overtime.index.*, ui.roles.groups.OvertimeAuthorization, ui.roles.permissions.* for the 4 Own/Team perms.
8. wayfinder:generate --with-form after route exists.
9. tests/Feature/OvertimeSectionTest.php: employee access, supervisor decide own/refused-other, admin access+bypass, no-permission 403, AC#4 regression (supervisor forbidden on organization-settings.edit).
10. pint --dirty, sa test --compact, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implementation complete.

- database/seeders/RoleSeeder.php: added RequestOwn:OvertimeAuthorization/ViewOwn:OvertimeAuthorization to EMPLOYEE_PERMISSIONS, ViewTeam:OvertimeAuthorization/ApproveTeam:OvertimeAuthorization to SUPERVISOR_PERMISSIONS, Manage:OvertimeAuthorization to ADMIN_PERMISSIONS (needed explicitly since it gates a route via permission: middleware, which the Gate::before super-admin bypass does not cover).
- app/Policies/OvertimeAuthorizationPolicy.php: viewTeam/approve/object mirroring LeavePolicy's canDecide shape exactly - a supervisor may only decide (approve/object) their own direct reports' records, checked via $authorization->user->supervisor_id === $user->id alongside the ApproveTeam:OvertimeAuthorization permission. Admins bypass via the existing super-admin Gate::before.
- New route GET /overtime -> OvertimeController@index (name overtime.index), gated by permission:RequestOwn:OvertimeAuthorization|ViewOwn:OvertimeAuthorization|ViewTeam:OvertimeAuthorization|ApproveTeam:OvertimeAuthorization|Manage:OvertimeAuthorization (Spatie's pipe-separated OR syntax) - any role holding at least one overtime permission can reach the section.
- app/Http/Controllers/OvertimeController.php + resources/js/pages/overtime/index.tsx: a deliberately empty section shell (Heading + "coming soon" placeholder) - KOL-44 (queue), KOL-45 (request flow) and KOL-42 (pactos) add their own screens under this same permission gate.
- app-sidebar.tsx: "Horas extra" nav item added to both employeeNavGroups (ViewOwn:OvertimeAuthorization) and adminNavGroups (ViewTeam/ApproveTeam/Manage:OvertimeAuthorization), following the existing canReviewTeamLeaves conditional pattern.
- Translations added to lang/es/ui.php and lang/en/ui.php: ui.nav.overtime, ui.overtime.index.*, ui.roles.groups.OvertimeAuthorization, ui.roles.permissions.* for all five overtime permissions.
- tests/Feature/OvertimeSectionTest.php (6 tests, AC #7): employee reaches the section; supervisor can decide their own report; supervisor refused for someone outside their team; admin reaches the section and decides any record via the gate; a user with no overtime permission gets 403; supervisor still forbidden from organization-settings.edit (AC #4 regression check).
- Fixed tests/Feature/Api/UserApiTest.php: two pre-existing tests hardcoded the employee role's permission count (9/10) and exact list; updated to 11/12 with the two new permissions included.
- wayfinder:generate --with-form run after adding the route.

Post-review fix (caught in browser QA): the admin permission was originally named Manage:Overtime, a different suffix from the other four (:OvertimeAuthorization). RolePresenter::groupKey() groups the Roles > Permissions screen by the raw suffix after the last colon, not the translated label, so the two suffixes rendered as two separate "Horas extraordinarias" boxes even though both translated to the same header - looked like a duplicate/overlapping section. Renamed to Manage:OvertimeAuthorization everywhere (RoleSeeder, routes/web.php, app-sidebar.tsx, both lang files) so all five permissions share one group. Also reworded the label from "Administrar horas extra y pactos" to "Administrar política y pactos de horas extra" for clarity against the per-record approve/view actions next to it. No actual permission overlap existed - Own/Team/tenant-admin scopes are disjoint; it was purely the grouping-key bug.

pint clean, npm run types:check clean, targeted suite (OvertimeSection/UserApi/RoleManagement/RolesPermissions) green. Full suite last verified green before this fix; touched files are backend-string-rename + translation-only, re-verify full suite before merge.

KOL-42 (pactos) is next: its routes/policy will gate on Manage:OvertimeAuthorization rather than inventing a new permission.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Created the overtime permission vocabulary (RequestOwn/ViewOwn/ViewTeam/ApproveTeam/Manage, all suffixed :OvertimeAuthorization for consistent Roles-screen grouping), seeded to employee/supervisor/admin respectively, plus a team-scoping OvertimeAuthorizationPolicy mirroring LeavePolicy. Added the section shell: GET /overtime behind an OR of the five permissions, a placeholder Inertia page, and a Spanish nav entry visible to whoever holds any of them. 6 new Pest tests cover every AC #7 case; full suite green (928 passed, 4 pre-existing skips). KOL-42 (pactos) is unblocked to consume Manage:OvertimeAuthorization next.
<!-- SECTION:FINAL_SUMMARY:END -->
