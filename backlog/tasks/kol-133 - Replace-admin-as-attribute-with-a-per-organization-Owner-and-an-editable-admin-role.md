---
id: KOL-133
title: >-
  Replace admin-as-attribute with a per-organization Owner and an editable admin
  role
status: To Do
assignee: []
created_date: '2026-09-25 12:41'
labels: []
dependencies: []
priority: high
type: feature
ordinal: 133000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Today, "admin" power comes from Gate::before(fn ($user) => $user->hasRole('admin') ? true : null) in AppServiceProvider — a blanket bypass tied to a role name. To make that safe, RolePresenter::PROTECTED_ROLES locks the admin role so nobody can edit its permissions or remove it from anyone, which makes it all-or-nothing: an organization can never customize what "admin" can do.

Goal: introduce a single Owner per Organization who unconditionally bypasses every authorization check inside their organization, regardless of what roles or permissions they hold. With that real safety net in place, the admin Spatie role becomes a normal, fully editable/assignable role (including assignable to/from the Owner with no real consequence, since the Owner's power does not come from holding that role).

Ownership is exactly one user per organization at all times (atomic transfer, never zero or two owners), transferable between users in the same organization, and the current owner cannot delete/deactivate their own account or leave the organization without transferring first.

The unrelated users.is_admin boolean (an HR/reporting field on the Employee form, unused for authorization) is removed as part of this effort since it now confusingly shares a name with the real admin concept.

See subtasks for the breakdown. KOL-95 (existing, open) is folded in as a dependency of the route-gating subtask, since making the admin role editable reopens exactly the inconsistency it flags (employees routes gated by role:admin instead of a named permission).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Exactly one Owner exists per organization at all times, including immediately after any transfer
- [ ] #2 The Owner retains full access within their organization even if the admin role is stripped of all permissions or removed from the Owner entirely
- [ ] #3 The admin Spatie role can be freely edited (permissions), assigned, and removed via existing UI, including to/from the Owner
- [ ] #4 The is_admin column and every reference to it are removed from the codebase
- [ ] #5 Employees CRUD routes are gated by named Spatie permissions instead of role:admin middleware
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
