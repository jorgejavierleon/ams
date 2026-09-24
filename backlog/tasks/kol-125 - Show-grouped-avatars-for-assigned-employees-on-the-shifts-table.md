---
id: KOL-125
title: Show grouped avatars for assigned employees on the shifts table
status: Done
assignee: []
created_date: '2026-09-24 08:59'
updated_date: '2026-09-24 09:00'
labels: []
dependencies: []
modified_files:
  - app/Http/Controllers/ShiftController.php
  - resources/js/pages/shifts/index.tsx
  - tests/Feature/ShiftManagementTest.php
type: enhancement
ordinal: 125000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The shifts (turnos) list's "asignaciones" column showed only a numeric count badge of assigned employees. Replace it with the same stacked-avatar grouping (AvatarGroup + "+N" overflow) already used for the users-per-role column on the Roles table (KOL-77), so admins can see at a glance who is assigned to a shift.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Shifts index assignments column renders AvatarGroup with the currently-active assigned employees' avatars instead of a plain count badge
- [x] #2 Backend eager-loads up to 5 active shift assignments with user id/name/avatar, keeping the existing assignments_count for the overflow bubble
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
Backend: ShiftController::index now eager-loads activeShiftAssignments (limit 5, ordered by start_date) with user (select id,name + media), reusing the existing HasMany scope rather than adding a new BelongsToMany relation, since pivot-date scoping inside a closure would need extra InteractsWithPivotTable methods not proven elsewhere in the codebase. Frontend: shifts/index.tsx swaps the Badge count for AvatarGroup (same component used in roles/index.tsx), passing users=avatars and total=assignments_count.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Implemented and verified: ShiftManagementTest (16 tests, 86 assertions) passes, including a new assertion that the assigned employee's id appears in shifts.data.0.avatars. Pint clean, tsc --noEmit clean, eslint clean on the touched frontend file.
<!-- SECTION:FINAL_SUMMARY:END -->
