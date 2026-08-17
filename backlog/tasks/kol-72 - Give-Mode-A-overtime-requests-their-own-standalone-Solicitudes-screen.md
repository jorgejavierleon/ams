---
id: KOL-72
title: Give Mode A overtime requests their own standalone Solicitudes screen
status: To Do
assignee: []
created_date: '2026-08-17 19:08'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-45
  - KOL-71
ordinal: 50000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Mode A overtime requests (an employee asking in advance to work overtime) are decided today in a tab of /overtime/queue, alongside the post-hoc excess approval that KOL-71 moves onto Jornadas. A request isn't tied to a computed Workday until the day is actually worked and calculated, so it doesn't belong on Jornadas either — it needs its own standalone screen under the Horas extra section, extracted from the queue rather than left stranded when the queue is decommissioned in KOL-74.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A supervisor or admin can list pending (and, via a status filter, decided) Mode A requests for their team/organization on a dedicated screen reachable from the Horas extra hub, independent of Jornadas and independent of the old queue
- [ ] #2 A request can be approved or rejected from this screen with the same notification behaviour as today (OvertimeRequestApproved/OvertimeRequestRejected)
- [ ] #3 Gating matches what the queue's Solicitudes tab enforced: reachable only when the tenant's authorisation mode allows requests, visible to ViewTeam/Manage, decidable only by ApproveTeam/Manage for one's own team
- [ ] #4 Pest tests cover listing, approving, rejecting, the mode-disabled 404, and cross-team refusal, exercised through the new standalone route rather than the queue
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
