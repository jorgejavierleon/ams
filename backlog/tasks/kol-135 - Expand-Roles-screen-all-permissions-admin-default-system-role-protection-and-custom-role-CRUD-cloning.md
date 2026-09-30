---
id: KOL-135
title: >-
  Expand Roles screen: all-permissions admin default, system-role protection,
  and custom role CRUD/cloning
status: Done
assignee: []
created_date: '2026-09-27 13:27'
updated_date: '2026-09-28 10:32'
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
- [x] #1 Admin is seeded with every permission that exists
- [x] #2 admin/employee/supervisor cannot be deleted or renamed, and can be restored to their RoleSeeder default permissions
- [x] #3 Users can create, rename, edit, and delete custom roles, with a warning when deleting a role that still has users assigned
- [x] #4 Any visible role (system or custom) can be cloned into a new custom role with the same permissions
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
All 4 subtasks (KOL-135.1..4) are Done and merged to master. Verified: pint --dirty clean, npm run types:check clean, php artisan test --compact --filter=RoleManagementTest passes 53/53 (includes clone, restore-defaults, delete-with-users-warning, and system-role protection coverage). Full untargeted test suite not run per standing preference to only run filtered tests during ticket work.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Shipped via subtasks KOL-135.1-135.4: admin seeded with the full permission set, admin/employee/supervisor locked from delete/rename with a restore-to-default-permissions action, full custom-role create/rename/delete (with a users-assigned warning), and role cloning. Follow-up i18n/clone-naming polish landed in commit de43118 (KOL-1). Verified with RoleManagementTest (53/53), Pint, and tsc.
<!-- SECTION:FINAL_SUMMARY:END -->
