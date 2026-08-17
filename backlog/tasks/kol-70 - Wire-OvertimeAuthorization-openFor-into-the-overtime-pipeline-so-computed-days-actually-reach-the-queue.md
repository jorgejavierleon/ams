---
id: KOL-70
title: >-
  Wire OvertimeAuthorization::openFor() into the overtime pipeline so computed
  days actually reach the queue
status: To Do
assignee: []
created_date: '2026-08-17 13:48'
labels:
  - overtime
  - backend
milestone: m-2
dependencies:
  - KOL-11
  - KOL-39
  - KOL-44
references:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
ordinal: 48000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Discovered while manually QAing KOL-47 (rest-day compensation): the overtime queue (KOL-44) was empty even with employees, shifts, marks and computed overtime in place. Traced the pipeline: WorkdayCalculator/the overtime:calculate job correctly compute Workday.calculated_overtime, but OvertimeAuthorization::openFor($workday) — the only way a pending authorisation record gets created — is never called anywhere in app/. It's only ever invoked from test helpers. KOL-11's own implementation notes deferred this explicitly ('Deliberately NOT wired into CalculateOvertime... KOL-44 is the natural caller'), but KOL-44's shipped implementation only reads existing OvertimeAuthorization rows; it never became that caller. The result: a real, silent gap between two already-Done tickets — no code path in production turns a computed day into something a supervisor can approve.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A workday with calculated_overtime > 0 results in a pending OvertimeAuthorization record without any manual step, for both the daily scheduled run and a backfilled date range
- [ ] #2 The calculation engine still never writes an approved or payable state directly — opening a pending record is structurally distinct from deciding one, preserving KOL-39's guarantee
- [ ] #3 Re-running the calculation for a day whose record already exists does not create a duplicate or disturb an existing decision
- [ ] #4 The mechanism is organization-scoped like the rest of the pipeline
- [ ] #5 Pest tests cover: a freshly computed day reaching the queue as pending, an idempotent re-run producing no duplicate, a re-run of an already-decided day leaving the decision intact, and tenant isolation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
