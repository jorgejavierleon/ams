---
id: KOL-66
title: Add a direct sidebar link to the overtime queue with a pending-review badge
status: To Do
assignee: []
created_date: '2026-08-15 21:31'
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
- [ ] #1 A sidebar item linking directly to overtime/queue is visible to any user who can currently reach the queue (ViewTeam/ApproveTeam/Manage:OvertimeAuthorization)
- [ ] #2 The item shows a badge with the combined count of pending OvertimeAuthorization rows and pending OvertimeRequest rows the current user is scoped to decide, matching the queue's own team/org scoping
- [ ] #3 The badge is hidden (or shows nothing) when the count is zero, matching the existing badge components' behavior
- [ ] #4 The existing Horas Extra hub link and its Pactos entry point are not removed by this change
- [ ] #5 Pest tests cover the shared count for a supervisor (team-scoped) and for an admin (org-wide)
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
