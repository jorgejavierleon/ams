---
id: KOL-94.6
title: 'Decide Import:Employee permission name and default role assignment'
status: Done
assignee:
  - '@me'
created_date: '2026-09-02 19:04'
updated_date: '2026-09-03 19:35'
labels:
  - 'wayfinder:grilling'
milestone: m-3
dependencies: []
parent_task_id: KOL-94
type: task
ordinal: 78000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Question

Following RoleSeeder's existing `View:X`/`Export:X` naming convention, decide the exact new permission name(s) for employee import (e.g. `Import:Employee`), and which roles get it by default in the seeder (admin only, or also supervisor?) — per the original ask that "the admin user, or anybody with a pertinent role/permission" should be able to import.
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @me
created: 2026-09-03 19:35
---
**Resolved.**

Exact permission: **`Import:Employee`** — single Spatie permission (guard `web`) gating the entire wizard: upload, mapping, preview, commit, and the error-report download, per KOL-94.5's already-locked single-permission route contract. Named following RoleSeeder's `Verb:Resource` convention (singular resource noun, matching `View:PayrollReport`/`ViewTeam:Leave`).

Default role assignment: **admin only**, added to `RoleSeeder::ADMIN_PERMISSIONS` (admin must hold it explicitly — `Gate::before(admin)` bypasses `can()`/policy checks but not Spatie's `permission:` route middleware, same as `View:PayrollReport`/`Export:PayrollReport`). Supervisor does NOT get it by default: supervisors currently hold zero Employee-record permissions (their grants are all team-scoped approval workflows over Leave/OvertimeAuthorization/Workday, never Employee CRUD), and the whole `employees` route group is walled off behind `role:admin` today. Bulk-creating/overwriting Employee records would be a bigger capability jump than anything a supervisor currently has. Not a hard wall — a tenant admin can grant `Import:Employee` to any role via the Roles screen later.

Side-finding, ruled out of scope for KOL-94: plain `employees` CRUD routes stay `role:admin`-gated (no Spatie permission at all) while `Import:Employee` is a real permission on the same model — a live inconsistency, tracked separately as KOL-95 (Align Employee CRUD route gating with Spatie permissions) rather than decided here.
---
<!-- COMMENTS:END -->
