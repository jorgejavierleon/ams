---
id: KOL-135
title: >-
  Expand Roles screen: all-permissions admin default, system-role protection,
  and custom role CRUD/cloning
status: To Do
assignee: []
created_date: '2026-09-27 13:27'
labels:
  - acl
dependencies: []
priority: medium
type: feature
ordinal: 141000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Today RoleController (app/Http/Controllers/RoleController.php) only supports viewing roles and editing a role's permissions — there is no create, rename, or delete. RolePresenter::PROTECTED_ROLES (app/Support/RolePresenter.php) only hides dt/saas; per KOL-133 the admin role is now fully editable with no delete/rename protection at all (and no delete route exists yet for any role). This epic adds: (1) the admin role seeded with every permission by default, (2) admin/employee/supervisor treated as un-deletable, un-renameable 'system roles' with a restore-to-default-permissions action, (3) full create/edit/delete for arbitrary custom roles, and (4) cloning any visible role's permissions into a new role. See subtasks for the breakdown.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Admin is seeded with every permission that exists
- [ ] #2 admin/employee/supervisor cannot be deleted or renamed, and can be restored to their RoleSeeder default permissions
- [ ] #3 Users can create, rename, edit, and delete custom roles, with a warning when deleting a role that still has users assigned
- [ ] #4 Any visible role (system or custom) can be cloned into a new custom role with the same permissions
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
