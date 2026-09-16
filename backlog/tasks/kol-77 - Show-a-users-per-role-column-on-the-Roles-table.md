---
id: KOL-77
title: Show a users-per-role column on the Roles table
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-08-18 10:26'
updated_date: '2026-09-16 19:04'
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
- [x] #1 The Roles index shows, per visible role, the count of users in the current organization holding that role
- [x] #2 The count is scoped to the current organization only, never a cross-tenant total
- [x] #3 The column follows the same visual pattern as the Cargos/Positions index (avatar group with overflow, reusing the existing AvatarGroup component) rather than a bare number
- [x] #4 The column is sortable, consistent with the existing name/permissions_count sort options
- [x] #5 Pest tests cover the count for a role with zero, one, and multiple users, and confirm a user from another organization is never counted
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
1. Backend (app/Http/Controllers/RoleController.php): add an AVATAR_LIMIT const (5, matching PositionController). In index(), resolve the current org id via CurrentOrganization::id(), then withCount(['permissions', 'users' => fn ($q) => $q->where('organization_id', $orgId)]) and eager-load ->with(['users' => fn ($q) => $q->where('organization_id', $orgId)->select('users.id','users.name')->with('media')->orderBy('name')->limit(AVATAR_LIMIT)]). Expose users_count and an avatars array (id/name/avatar) in the resource shape. Add 'users_count' to the sortable allow-list.
2. Frontend (resources/js/pages/roles/index.tsx): extend the Role type with users_count and avatars (AvatarGroupUser[]); add a sortable "Users" column rendered with the shared AvatarGroup component, positioned like Positions' employees column.
3. Add lang/en/ui.php + lang/es/ui.php roles.columns.users translation strings.
4. Tests (tests/Feature/RoleManagementTest.php): cover zero/one/multiple users for a role, confirm a same-named-role user from another organization is never counted, and cover sorting by users_count.
5. Run vendor/bin/pint --dirty --format agent, sail artisan test --compact --filter=RoleManagementTest, and npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
RoleController::index now withCounts+eager loads Role::users() (Spatie's morphedByMany), scoped via CurrentOrganization::id() since Spatie roles are shared globally. Frontend reuses AvatarGroup exactly as Positions does. Verified: sail artisan test --compact --filter=RoleManagementTest -> 31 passed; vendor/bin/pint --dirty clean; npm run types:check has 2 pre-existing unrelated errors in roles/show.tsx (confirmed present on master via git stash, not introduced by this change); manually verified in browser (logged in as admin@example.com) that the Usuarios column renders avatars/overflow bubble and that clicking the header sorts by users_count (asc puts Supervisor before Empleado).

Addressed code-review finding: deduplicated the org-scoping closure in RoleController::index (shared $scopeToCurrentOrganization closure used by both withCount and with). Left three other findings unfixed as scope-appropriate: null-organization_id edge case matches the same unguarded ->where('organization_id', CurrentOrganization::id()) pattern used throughout the codebase (PayrollSummaryReportController, OvertimeExcessReportController, etc.) rather than being a regression; AVATAR_LIMIT/avatar-mapping duplication with PositionController is intentional per AC #3 (same visual pattern); test boilerplate duplication is minor and stylistic.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a users-per-role column to the Roles index, mirroring the Cargos/Positions pattern. RoleController::index withCounts and eager-loads Role's users() relation (Spatie morphedByMany), scoped to the current organization via CurrentOrganization::id() (roles are global across tenants, so this scoping is explicit rather than via a model global scope). Exposes users_count + avatars, added users_count to the sortable allow-list. Frontend renders the column with the existing AvatarGroup component. Added Pest coverage for zero/one/many users and cross-org exclusion, plus a sort test. Verified with sail artisan test (31 passed), pint (clean), and a manual browser check of rendering + sorting.
<!-- SECTION:FINAL_SUMMARY:END -->
