---
id: KOL-42
title: >-
  Manage pactos de horas extraordinarias with their three-month statutory
  validity
status: To Do
assignee: []
created_date: '2026-08-06 02:52'
updated_date: '2026-08-08 15:27'
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
- [ ] #5 Agreements are listed, created, edited and revoked from the UI in Spanish by a user holding the right permission
- [ ] #6 Agreements are organization-scoped and never visible across tenants
- [ ] #7 When no agreement covers the worked date, the record is flagged with that specific reason and can still be approved with a mandatory written justification; the absence of an agreement never blocks payment
- [ ] #8 Pest tests cover a valid agreement, one exceeding three months, a renewal, an expired agreement at approval time, a missing agreement approved with a written justification and refused without one, and tenant isolation
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
author: @jorge
created: 2026-08-08 15:27
---
Amended, and the KOL-37 tenant switch it referenced is gone (removed in KOL-57).

Art. 32 requires overtime to be agreed in writing, but the absence of that agreement does not make the hours cease to be overtime: the DT reality criterion — stated by the PRD itself at line 22 — holds that hours worked with the employer's knowledge are payable whether or not a written pacto exists. A rule making a record unapprovable without one therefore produces an unlawful outcome, not a conservative one.

The correct shape is already in the source: Res. 38 art. 45.2 says of the excessive-shift alert that it 'no impedirá la carga de la jornada, sino que sólo constituirá un aviso para el empleador'. KOL-41 implements exactly that for legal caps. A missing pacto is a flag demanding a written justification, never a bar. See decision-1.

Everything else in this task stands: the three-month ceiling, renewal creating a new record rather than extending, validity judged by the date worked, and the expiry alert. Caveat carried from decision-1: the Código del Trabajo text is not in the repo, so the art. 32 reading here must be confirmed with the labor advisor before this task is finalised.
---
<!-- COMMENTS:END -->
