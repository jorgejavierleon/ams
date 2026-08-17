---
id: KOL-73
title: Add a read-only Horas extra aprobadas ledger
status: To Do
assignee: []
created_date: '2026-08-17 19:08'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-11
ordinal: 51000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Once overtime approval moves onto Jornadas (KOL-71) and the queue concept disappears (KOL-74), there is no longer a screen to simply browse what has already been approved without hunting through Jornadas day by day. This adds a read-only ledger of approved overtime, reachable from the Horas extra hub, for review/audit purposes. Corrections still happen on Jornadas — this screen has no actions.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A supervisor or admin can list approved overtime records for their team/organization, filterable by employee and date range, showing employee, date, calculated/authorized/final hours, compensation type, approver and approval timestamp
- [ ] #2 The list reads only OvertimeAuthorization::approved() records and offers no approve/object/edit action
- [ ] #3 The list is organization-scoped and, for a non-admin, scoped to the viewer's team
- [ ] #4 Pest tests cover listing, filtering by employee and date range, and tenant/team isolation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
