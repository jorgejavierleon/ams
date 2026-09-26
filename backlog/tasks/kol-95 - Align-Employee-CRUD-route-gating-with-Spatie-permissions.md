---
id: KOL-95
title: Align Employee CRUD route gating with Spatie permissions
status: Done
assignee: []
created_date: '2026-09-03 19:35'
updated_date: '2026-09-26 11:25'
labels: []
dependencies: []
references:
  - >-
    backlog/tasks/kol-94.6 -
    Decide-Import-Employee-permission-name-and-default-role-assignment.md
ordinal: 82000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The employees route group (routes/web.php, Route::resource('employees', ...) plus toggle-active/export) is gated entirely by role:admin middleware, not a Spatie permission. Every other resource with equivalent sensitivity (PayrollReport, Leave, OvertimeAuthorization, Workday) is gated by a named permission (View:X/Export:X/Manage:X) per RoleSeeder's convention, and Gate::before(admin) only bypasses can()/policy checks, not the permission: route middleware. KOL-94.6 introduces Import:Employee as a real Spatie permission on the same underlying Employee model while plain CRUD on that model stays role-gated, which is a live inconsistency surfaced while designing the bulk import framework (KOL-94) — noted there as out of scope for that effort.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Decide whether employees CRUD routes should move from role:admin to a named Spatie permission (e.g. View:Employee/Manage:Employee), following RoleSeeder's Verb:Resource convention
- [x] #2 If converted, RoleSeeder grants the new permission(s) to admin by default so existing behavior is unchanged
- [ ] #3 If not converted, the decision to keep role:admin gating is documented with a reason
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Comments

<!-- COMMENTS:BEGIN -->
created: 2026-09-25 12:42
---
Superseded/absorbed by KOL-133.4, part of the KOL-133 Owner/admin-role initiative. Leaving status as-is until KOL-133.4 ships and closes this decision.
---

author: jorgejavierleon@gmail.com
created: 2026-09-26 11:25
---
Resolved by KOL-133.4: employees CRUD routes now gated by View:Employee/Manage:Employee, RoleSeeder grants both to admin by default.
---
<!-- COMMENTS:END -->
