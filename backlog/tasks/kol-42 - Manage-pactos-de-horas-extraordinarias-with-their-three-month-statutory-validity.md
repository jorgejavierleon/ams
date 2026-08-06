---
id: KOL-42
title: >-
  Manage pactos de horas extraordinarias with their three-month statutory
  validity
status: To Do
assignee: []
created_date: '2026-08-06 02:52'
updated_date: '2026-08-06 02:56'
labels:
  - overtime
  - backend
  - frontend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-11
  - KOL-37
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 800
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 7.6. Código del Trabajo art. 32 requires overtime to be agreed in writing, for a maximum of three months, renewable, and only for transitory needs of the business. A tenant that has switched on the pacto requirement in KOL-37 cannot approve overtime without a valid one behind it.

CRUD for the agreement itself: employee, date range, status. The three-month ceiling is a validation on the range, and renewal creates a new agreement rather than extending the old one — extending in place would destroy the evidence of what was agreed when, which is the only reason the record exists.

Behaviour that matters at the edges:
- An agreement about to expire raises an alert, so overtime does not silently become unapprovable mid-period.
- An overtime record links to a valid agreement when one exists. When the tenant requires one and none covers the date, the record stays pending with that stated as the explicit reason — not a generic pending, but *pending because there is no valid pacto*, so the person looking at the queue knows what to do about it.
- Validity is judged against the date the overtime was worked, not the date it is being approved. Approving in early September an hour worked in late August must consult the agreement that was in force in late August.

UI in Spanish, managed by users holding the right permission from KOL-43. The list follows the shared DataTable foundation used by every other list screen in the app.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 An overtime agreement can be created for an employee over a date range, and a range longer than three months is refused with a Spanish message citing the reason
- [ ] #2 Renewal produces a new agreement rather than extending an existing one, so the history of what was agreed and when is preserved
- [ ] #3 An agreement nearing expiry raises an alert to the users responsible for it
- [ ] #4 An overtime record links to the agreement covering its worked date, judged by the date worked and not the date approved
- [ ] #5 When the tenant requires an agreement and none covers the date, the overtime record stays pending with that specific reason recorded, distinguishable from any other pending reason
- [ ] #6 Agreements are listed, created, edited and revoked from the UI in Spanish by a user holding the right permission
- [ ] #7 Agreements are organization-scoped and never visible across tenants
- [ ] #8 Pest tests cover a valid agreement, one exceeding three months, a renewal, an expired agreement at approval time, approval blocked for a missing agreement when the tenant requires one, and tenant isolation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
