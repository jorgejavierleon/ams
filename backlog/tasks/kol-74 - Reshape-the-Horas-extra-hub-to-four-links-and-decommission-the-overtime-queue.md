---
id: KOL-74
title: Reshape the Horas extra hub to four links and decommission the overtime queue
status: To Do
assignee: []
created_date: '2026-08-17 19:08'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-71
  - KOL-72
  - KOL-73
ordinal: 52000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Final step of the Jornadas-overtime refactor: once approval lives on Jornadas (KOL-71), Mode A requests have their own screen (KOL-72), and the approved ledger exists (KOL-73), the Horas extra hub and the old combined queue are cleaned up. The hub becomes exactly four links: Solicitudes, Pactos, Saldo de descanso, Horas extra aprobadas. The old /overtime/queue screen, its controller, its routes and its tests are removed.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The Horas extra hub (/overtime) shows exactly four links, gated by the same permissions their destination screens already enforce: Solicitudes, Pactos, Saldo de descanso, Horas extra aprobadas
- [ ] #2 /overtime/queue and its controller actions for post-hoc approve/object/bulk-decide no longer exist; the route returns not-found
- [ ] #3 The sidebar link and pending-review badge added for the old queue now point at Jornadas (or wherever pending overtime is now surfaced) instead of a dead route
- [ ] #4 KOL-67 is amended or closed as superseded, since its premise (a direct link to the queue) no longer applies
- [ ] #5 No test file still exercises the removed queue routes; the superseded test files are deleted, not left failing
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
