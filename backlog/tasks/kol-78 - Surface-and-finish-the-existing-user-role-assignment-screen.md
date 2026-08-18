---
id: KOL-78
title: Surface and finish the existing user role-assignment screen
status: To Do
assignee: []
created_date: '2026-08-18 10:26'
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
- [ ] #1 An admin can reach a 'manage roles' action for a given user from the Employees index or show page (or another sensible existing surface), without knowing the URL in advance
- [ ] #2 From that screen, an admin can assign and remove any non-protected role for the user, and the change is saved and reflected immediately
- [ ] #3 The page text is fully localized (Spanish/English via lang/*/ui.php), consistent with the rest of the admin section
- [ ] #4 The screen matches the app's existing layout conventions (breadcrumbs, heading, spacing) used by comparable admin pages like Roles and Positions
- [ ] #5 Only users holding role:admin can reach or submit this screen; a non-admin is refused
- [ ] #6 Pest tests cover viewing a user's current roles, assigning a role, removing a role, and the non-admin refusal
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
