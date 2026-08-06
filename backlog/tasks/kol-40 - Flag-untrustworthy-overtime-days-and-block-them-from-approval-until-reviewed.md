---
id: KOL-40
title: Flag untrustworthy overtime days and block them from approval until reviewed
status: To Do
assignee: []
created_date: '2026-08-06 02:50'
updated_date: '2026-08-06 02:57'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-39
  - KOL-37
  - KOL-11
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 500
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 7.4. An anomaly is not a legal-cap breach — that is KOL-41. An anomaly means *the underlying data is not trustworthy enough to pay from*, and it blocks a day from reaching approved until a human has looked at it. This is the mechanism that stops a forgotten clock-out from turning into five hours of paid overtime.

The conditions to flag, all derivable from data that already exists:
- **No assigned shift that day.** There is no baseline to measure excess against. `WorkdayStatus::Irregular` already marks this case.
- **Only one mark for the day** — clock-in without clock-out or the reverse. `WorkdayStatus::Incomplete` already marks this case.
- **The contract is not active on the marked date.** `users.contract_type`, `contract_start_date` and `contract_end_date` are on the employee record.
- **The mark fell outside the expected geofence**, for tenants using geolocation. `marks.geo_status` carries the verdict, stamped server-side.
- **Period volume over the tenant threshold** — the weekly figure configured in KOL-37, default 10h. A signal of shift misuse or systematic marking error rather than of any one bad punch.

Reuse `App\Enums\WorkdayStatus` for the first two rather than recomputing the same condition a second way; the value of a separate flag is that it carries a reason a supervisor can read and act on, not that it re-derives the status.

Two constraints from Resolución 38 art. 45.2 and PRD section 9 that shape the design: a flag **never blocks saving or loading** a mark or a shift — it is advisory at the point of entry and blocking only at the point of approval. And flags are recomputed by the engine, so a flag that no longer applies after a mark correction disappears on its own rather than needing manual clearing.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A day is flagged when the employee has no assigned shift, when only one of the two marks exists, when the contract is not active on that date, when a mark fell outside the geofence, and when the period volume exceeds the tenant threshold
- [ ] #2 Each flag carries a machine-readable reason and a Spanish human-readable explanation a supervisor can act on
- [ ] #3 A flagged day cannot reach approved status through any path until a human has reviewed it
- [ ] #4 Flagging never blocks saving a mark, saving a shift or running the calculation, per Resolución 38 art. 45.2
- [ ] #5 Flags are recomputed by the calculation engine, so a flag whose cause was corrected disappears without manual intervention
- [ ] #6 The existing `WorkdayStatus` values are reused for the missing-shift and incomplete-day conditions rather than the condition being re-derived separately
- [ ] #7 The volume threshold read is the tenant one from KOL-37, not a constant
- [ ] #8 Pest tests cover each flag condition individually, a day with several flags at once, a flag clearing after the mark is corrected, and the fact that a flagged day cannot be approved
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
