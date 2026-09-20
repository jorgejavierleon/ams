---
id: KOL-119
title: Add role-aware widgets to the dashboard (employees vs managers/admins)
status: In Progress
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-20 11:46'
updated_date: '2026-09-20 12:11'
labels: []
dependencies: []
ordinal: 112000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The dashboard currently only shows something real for employees who hold ClockOwn:Mark (the attendance ClockCard, resources/js/pages/dashboard.tsx). Everyone else — most supervisors and admins — sees empty placeholder boxes. This epic adds data-driven widgets grounded in what the app already tracks, split by what each role actually needs to act on. Employees get a personal action-items card; supervisors/admins get team-approval and team-visibility widgets; everyone gets an upcoming-holidays list. Two of the counts needed (pending mark-modification reviews, pending document signatures, pending overtime requests) are already computed and shared as auth.pendingModificationsCount / auth.pendingSignaturesCount / auth.pendingOvertimeRequestsCount in app/Http/Middleware/HandleInertiaRequests.php for the sidebar badges — subtasks should reuse them rather than duplicate the query. A charting/trend widget was considered but is deliberately out of scope: it needs a new frontend dependency (no charting library is installed) and is tracked as a separate decision, not part of this epic.
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
