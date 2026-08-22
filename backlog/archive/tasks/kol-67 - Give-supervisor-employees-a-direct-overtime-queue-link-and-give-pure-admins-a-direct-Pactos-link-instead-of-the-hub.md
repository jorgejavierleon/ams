---
id: KOL-67
title: >-
  Give supervisor-employees a direct overtime-queue link, and give pure admins a
  direct Pactos link instead of the hub
status: To Do
assignee: []
created_date: '2026-08-16 15:33'
updated_date: '2026-08-21 09:54'
labels:
  - overtime
  - frontend
  - ux
dependencies:
  - KOL-66
ordinal: 47000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
KOL-66 adds a direct sidebar link to the overtime queue, but only in the admin nav group -- an HR admin without an employee record. It leaves two related gaps unaddressed. First, a supervisor who is also an employee (and therefore renders the self-service employeeNavGroups, never the admin nav) has no direct path to overtime/queue at all today; they can only reach it by first opening the Horas Extra hub and clicking Cola, which is exactly the extra-click problem KOL-66 fixes for admins but not for them, even though ApproveTeam:OvertimeAuthorization already qualifies them for the queue and its badge count. Second, once the queue has a direct admin-nav link, the Horas Extra hub carries no remaining unique content for a pure admin (they lack RequestOwn/ViewOwn:OvertimeAuthorization, so the hub shows nothing but the Cola and Pactos buttons) -- keeping it in Aprobaciones just re-adds the click KOL-66 removed, for the one destination (Pactos) it still uniquely leads to. This closes both gaps: a supervisor-employee gets the same direct, badged queue link a pure admin now has, and a pure admin gets a direct Pactos link (in the Aprobaciones group) instead of routing through an otherwise-empty hub.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A user who can reach overtime/queue (ViewTeam/ApproveTeam/Manage:OvertimeAuthorization) but does not render the admin nav -- i.e. a supervisor who is also an employee -- sees a direct sidebar link to the queue in their nav, carrying the same combined pending-count badge KOL-66 built for the admin nav, scoped identically (team-only for ApproveTeam/ViewTeam, org-wide for Manage)
- [ ] #2 A user who renders the admin nav and holds Manage:OvertimeAuthorization no longer sees the Horas Extra hub link in Aprobaciones
- [ ] #3 A user who renders the admin nav and holds Manage:OvertimeAuthorization sees a direct sidebar link to Pactos in the Aprobaciones group in its place
- [ ] #4 A supervisor-employee's existing Horas Extra hub link (their own overtime requests/history) is unchanged in position and behavior, including its own Cola button
- [ ] #5 A rank-and-file employee with only ViewOwn/RequestOwn:OvertimeAuthorization sees no queue link and no Pactos link, matching today's behavior
- [ ] #6 The overtime/index.tsx hub page itself (its buttons and content) is unchanged by this ticket
- [ ] #7 All new nav labels are in Spanish
- [ ] #8 Coverage (feature or browser test) confirms each of the three audiences (pure admin, supervisor-employee, rank-and-file employee) sees exactly the expected set of overtime-related nav items
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Comments

<!-- COMMENTS:BEGIN -->
created: 2026-08-17 19:08
---
Flagged while planning the Jornadas-overtime refactor (KOL-71..74): once KOL-74 ships, /overtime/queue no longer exists, so this task's premise ('a direct overtime-queue link') is gone. Needs rescoping once KOL-74 lands — likely 'a direct Jornadas link filtered to pending overtime' for supervisor-employees, plus the admin-Pactos-link half may still stand as originally scoped. Do not implement as currently written.
---

author: @jorge
created: 2026-08-21 09:54
---
Closed as superseded by KOL-74 on 2026-08-21: with the queue decommissioned, this task's premise (a direct sidebar link to /overtime/queue for supervisor-employees) no longer applies. The admin-Pactos-link half (AC #2/#3) may still be worth doing, and a supervisor-facing 'pending overtime' shortcut into Jornadas could replace the queue-link idea, but both are new scope requiring fresh design -- not implemented as part of KOL-74. Archiving; re-create as a fresh task if wanted.
---
<!-- COMMENTS:END -->
