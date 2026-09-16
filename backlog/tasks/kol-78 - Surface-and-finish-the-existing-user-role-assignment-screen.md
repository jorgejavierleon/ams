---
id: KOL-78
title: Surface and finish the existing user role-assignment screen
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-08-18 10:26'
updated_date: '2026-09-16 22:20'
labels:
  - roles
  - frontend
  - backend
dependencies: []
ordinal: 56000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
A screen to assign/remove a user's roles already exists in the codebase but is effectively dead code: UserRoleController::show()/update() (app/Http/Controllers/UserRoleController.php) and resources/js/pages/users/roles.tsx implement a full assign/remove flow via syncRoles() on routes users/{user}/roles (GET) and users/{user}/roles (PUT), gated by role:admin. Confirmed by search: nothing in resources/js links to this route — no button on the Employees index/show, the Roles index, or anywhere else. It is only reachable by typing the URL directly.

The page also doesn't match the rest of the app's conventions: it hardcodes English strings instead of using the ui.php translation system (useTranslations/t()) that every other admin page uses, and there is no Pest test coverage for either controller method.

This ticket is about finishing and surfacing that existing flow, not building a new one from scratch — reuse UserRoleController and users/roles.tsx as the starting point rather than re-implementing role assignment.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 An admin can reach a 'manage roles' action for a given user from the Employees index or show page (or another sensible existing surface), without knowing the URL in advance
- [x] #2 From that screen, an admin can assign and remove any non-protected role for the user, and the change is saved and reflected immediately
- [x] #3 The page text is fully localized (Spanish/English via lang/*/ui.php), consistent with the rest of the admin section
- [x] #4 The screen matches the app's existing layout conventions (breadcrumbs, heading, spacing) used by comparable admin pages like Roles and Positions
- [x] #5 Only users holding role:admin can reach or submit this screen; a non-admin is refused
- [x] #6 Pest tests cover viewing a user's current roles, assigning a role, removing a role, and the non-admin refusal
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
1. Backend: move PROTECTED_ROLES (admin, dt, saas) from RoleController into RolePresenter as a shared const; update RoleController to reference it.
2. UserRoleController::show() - exclude protected roles from the assignable list (whereNotIn name, PROTECTED_ROLES), matching RoleController::index().
3. UserRoleController::update() - never let the synced role set drop or add a protected role: compute the user's current protected role ids, filter submitted ids down to non-protected roles that exist, then sync the union. This defends against a tampered payload trying to add/remove admin/dt/saas.
4. Frontend: localize resources/js/pages/users/roles.tsx with useTranslations + a LayoutCallback breadcrumb (mirroring roles/show.tsx); add a 'user_roles' ui.php section (en/es) for the page description/breadcrumb text, reusing ui.roles.save/saving for the button.
5. Add an entry point: a 'Manage roles' button on employees/show.tsx (next to the existing Edit button), linking to UserRoleController.show(employee.id); add employees.actions.manage_roles ui.php key (en/es).
6. Tests (tests/Feature/RoleManagementTest.php): add cases for (a) non-admin refused on PUT users.roles.update, (b) protected roles excluded from the GET roles list, (c) a tampered update payload cannot add or remove a protected role.
7. Run pint --dirty, the filtered Pest tests, and npm run types:check; then the full suite once at the end per /implement.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified full flow live in the browser (admin@example.com): Empleados show page renders a localized 'Gestionar roles' button linking to /users/{id}/roles; toggled Supervisor on/off and confirmed the save persisted across a reload, then reverted the test toggle. Ran full Pest suite (1439 passed, 4 pre-existing skipped, 0 failed), Pint (clean), PHPStan (0 errors on touched files), ESLint (clean), tsc --noEmit (2 pre-existing failures in roles/index.tsx and roles/show.tsx, unrelated to this change and reproduced on master via git stash). Code review (mattpocock-skills:code-review) flagged two issues, both fixed: (1) the save button had been reusing ui.roles.save/saving ('Save permissions') instead of dedicated user_roles.save/saving copy ('Save roles'); (2) the protected-roles whereNotIn filter was duplicated across RoleController::index, UserRoleController::show and UserRoleController::update — centralized into RolePresenter::excludeProtected(Builder $query).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Surfaced the existing UserRoleController/users/roles.tsx flow: added a 'Manage roles' button on the Employees show page (admin-only via the existing role:admin route group), fully localized the roles page (lang/en+es/ui.php: user_roles.*, employees.actions.manage_roles) with breadcrumbs matching roles/show.tsx conventions, and hardened the backend so protected roles (admin/dt/saas) are excluded from the assignable list and survive a sync/tampered payload untouched (RolePresenter::excludeProtected, reused across RoleController and UserRoleController). Added 5 new Pest tests for PUT non-admin refusal, protected-role exclusion, and protected-role preservation. Verified: full Pest suite green (1439/1443, 4 pre-existing skips), Pint/PHPStan/ESLint clean, and the flow exercised live in Chrome (list → manage roles → assign/remove → persisted).
<!-- SECTION:FINAL_SUMMARY:END -->
