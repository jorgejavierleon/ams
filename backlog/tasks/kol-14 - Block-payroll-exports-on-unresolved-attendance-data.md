---
id: KOL-14
title: Block payroll exports on unresolved attendance data
status: To Do
assignee: []
created_date: '2026-08-04 11:12'
labels:
  - payroll-reports
  - backend
  - frontend
milestone: m-0
dependencies:
  - KOL-13
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 13000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-2, and user story 2 of the PRD: RRHH must be warned before sending wrong numbers to remuneraciones. Talana blocks the traspaso of any worker with an invalid mark until it is corrected; the PRD deliberately adopts a softer version — **a blocking warning that lists what is wrong and requires explicit confirmation, not a hard refusal** (a client closing payroll on a deadline must still be able to export while knowing exactly what is unresolved).

What counts as unresolved, from data that already exists:
- Workdays in the period whose `status` is irregular or incomplete (`app/Enums/WorkdayStatus.php`) — a missing exit mark makes worked_time and extra_time meaningless for that day.
- `MarkModification` records still `Pending` (`app/Enums/MarkModificationStatus.php`) — the figures will change once a supervisor rules on them. `app/Models/Workday.php` already exposes a `pendingMarkModifications()` relation, so this is a query, not new plumbing.
- Any period overlapping an open technical incident (`app/Models/Incident.php` with a null end_time) is worth surfacing too, since attendance may be missing for reasons outside the employee's control.

The result must be actionable: not 'there are 14 problems' but which employees, which days, and why — with a route back to the screen where each is fixed.

Note the sequencing risk: this check needs to run on the same employee/period selection the export uses, so it belongs next to the aggregation service rather than inside any one report.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A service returns, for a period and employee selection, every unresolved item grouped by employee and day, each with a reason
- [ ] #2 Pending mark modifications, irregular workdays and incomplete workdays are all detected; open technical incidents overlapping the period are surfaced as informational
- [ ] #3 The UI shows the findings before an export runs, and the export only proceeds after an explicit confirmation from the user — never silently
- [ ] #4 A clean period produces no warning and no extra confirmation step, so the check does not add friction when there is nothing wrong
- [ ] #5 Each listed finding links to where it is resolved (the workday or the mark modification review screen)
- [ ] #6 The confirmation the user gave is recorded with the export audit entry, so it is later provable that they were warned
- [ ] #7 The check is bounded in query count for a 500-employee period and is organization-scoped
- [ ] #8 Pest tests cover a clean period, a period with pending modifications, one with irregular and incomplete days, and confirm the export is reachable only after confirmation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
