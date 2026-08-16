---
id: KOL-66
title: Add a direct sidebar link to the overtime queue with a pending-review badge
status: Done
assignee: []
created_date: '2026-08-15 21:31'
updated_date: '2026-08-16 18:51'
labels:
  - overtime
  - frontend
  - ux
dependencies:
  - KOL-45
ordinal: 46000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Reaching the pending-overtime queue (overtime/queue) from the sidebar currently requires an admin/supervisor to click through the Horas Extra hub first, then into the queue. This is a UX complaint raised while working KOL-45: it is too many clicks to reach the thing that actually needs regular review. Add a direct sidebar item (in the Aprobaciones/admin nav group, alongside the existing team-leaves items which already link directly to their list) that goes straight to overtime/queue, carrying a badge with the count of items awaiting the current user's decision — mirroring the existing badge pattern already used for auth.pendingModificationsCount and auth.pendingSignaturesCount in HandleInertiaRequests.php and resources/js/components/app-sidebar.tsx. The count should combine both what needs review: pending OvertimeAuthorization rows (the Jornadas tab) and pending OvertimeRequest rows (the Solicitudes tab from KOL-45), scoped the same way the queue itself is scoped (team-only for a supervisor with ApproveTeam/ViewTeam:OvertimeAuthorization, org-wide for Manage:OvertimeAuthorization).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A sidebar item linking directly to overtime/queue is visible to any user who can currently reach the queue (ViewTeam/ApproveTeam/Manage:OvertimeAuthorization)
- [x] #2 The item shows a badge with the combined count of pending OvertimeAuthorization rows and pending OvertimeRequest rows the current user is scoped to decide, matching the queue's own team/org scoping
- [x] #3 The badge is hidden (or shows nothing) when the count is zero, matching the existing badge components' behavior
- [x] #4 Pest tests cover the shared count for a supervisor (team-scoped) and for an admin (org-wide)
- [x] #5 This change does not relocate or remove the existing Horas Extra hub link or its Pactos entry point in the admin nav; any restructuring of where those links live is tracked separately (see follow-up ticket).
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
1. HandleInertiaRequests.php: inject OrganizationSettings via constructor, add pendingOvertimeCount(Request) computing the scoped combined count (Manage:OvertimeAuthorization -> org-wide, ViewTeam/ApproveTeam -> supervisor-scoped, otherwise 0), mirroring OvertimeQueueController::index's isAdmin/supervisorId/showRequests logic. Share it as auth.pendingOvertimeCount.
2. app-sidebar.tsx: add a direct 'Cola de horas extra' item to adminNavGroups' Aprobaciones group (guarded by canManageOvertime, badge: auth.pendingOvertimeCount), linking to overtime/queue's index route. Leave the existing Horas Extra hub link untouched (AC #5).
3. lang/es/ui.php + lang/en/ui.php: add the new nav label key.
4. Pest: new tests/Feature/OvertimeQueueBadgeTest.php asserting auth.pendingOvertimeCount for a team-scoped supervisor and an org-wide admin, combining pending OvertimeAuthorization + pending OvertimeRequest (mode Combined), zero when nothing pending, and zero for a user without any overtime permission.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented: HandleInertiaRequests now shares auth.pendingOvertimeCount (pending OvertimeAuthorization + pending OvertimeRequest when the org's mode allows requests), scoped org-wide for Manage:OvertimeAuthorization and team-scoped for ViewTeam/ApproveTeam, mirroring OvertimeQueueController::index. app-sidebar.tsx adds a direct 'Cola de horas extra' item (ListChecks icon) to the Aprobaciones group next to the existing Horas Extra hub link, carrying the badge. New lang key ui.nav.overtime_queue in es/en. 6 new Pest tests in tests/Feature/OvertimeQueueBadgeTest.php cover team scoping, org-wide scoping, combined-mode counting, post-hoc-mode exclusion, zero-count, and no-permission zero-count. Full suite: 1030/1031 passing (1 pre-existing unrelated failure in UpcomingShiftsApiTest, a date-dependent test this branch does not touch). pint and npm run types:check both clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added auth.pendingOvertimeCount shared prop (HandleInertiaRequests.php) combining pending OvertimeAuthorization + pending OvertimeRequest, scoped org-wide for Manage:OvertimeAuthorization and team-scoped for ViewTeam/ApproveTeam, mirroring OvertimeQueueController::index. Added a direct 'Cola de horas extra' sidebar item to the Aprobaciones group carrying the badge, leaving the existing Horas Extra hub link untouched. Verified with 6 new Pest tests (tests/Feature/OvertimeQueueBadgeTest.php) plus the full suite (1030/1031, the 1 failure pre-existing and unrelated); pint, tsc, eslint, prettier all clean.
<!-- SECTION:FINAL_SUMMARY:END -->
