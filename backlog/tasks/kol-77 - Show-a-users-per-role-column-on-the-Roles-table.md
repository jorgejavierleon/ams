---
id: KOL-77
title: Show a users-per-role column on the Roles table
status: To Do
assignee: []
created_date: '2026-08-18 10:26'
labels:
  - roles
  - frontend
  - backend
dependencies: []
ordinal: 55000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The Roles index (/roles) currently lists only each role's name and permission count (RoleController::index, resources/js/pages/roles/index.tsx). The Cargos/Positions index already solves the equivalent problem for positions: PositionController::index() eager-loads active_users_count plus a handful of avatars via the activeUsers relation, and resources/js/pages/positions/index.tsx renders them with the shared AvatarGroup component. Roles should show the same thing, so an admin can see at a glance how many users hold each role.

Important nuance: Spatie roles are NOT organization-scoped — the five seeded roles (admin/employee/supervisor/dt/saas) are shared globally across every tenant (see database/seeders/RoleSeeder.php), and RoleController::index() already excludes the three protected roles (admin/dt/saas), leaving only supervisor and employee visible. A naive Role::withCount('users') would count users across every tenant in the database, which is meaningless on a single tenant's admin screen — the count must be scoped to the current organization's users only (users.organization_id = current org), the same way PositionController scopes through Position's own org-scoped model.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The Roles index shows, per visible role, the count of users in the current organization holding that role
- [ ] #2 The count is scoped to the current organization only, never a cross-tenant total
- [ ] #3 The column follows the same visual pattern as the Cargos/Positions index (avatar group with overflow, reusing the existing AvatarGroup component) rather than a bare number
- [ ] #4 The column is sortable, consistent with the existing name/permissions_count sort options
- [ ] #5 Pest tests cover the count for a role with zero, one, and multiple users, and confirm a user from another organization is never counted
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
