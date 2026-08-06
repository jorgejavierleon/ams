---
id: KOL-38
title: >-
  Redefine calculated overtime as shift-end excess, to the second, never
  inferred
status: To Do
assignee: []
created_date: '2026-08-06 02:49'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-36
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 300
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
**The current overtime number does not mean what the PRD says overtime means, and it is wrong for a whole class of shifts.**

`app/Services/WorkdayCalculator.php` computes `extra_time` in a SQL `CASE` (around line 168) as *in-to-out span minus scheduled shift duration*. The PRD section 7.2 defines shift excess as *last mark minus shift end time*. These are different numbers whenever an employee arrives early, and only the second one is what an employer authorises. Worse, the expression wraps every value in `TIME()`, so a shift crossing midnight produces a negative or nonsensical difference — the same defect affects `in_time_difference` and `out_time_difference`.

Two further requirements from Resolución 38 art. 44: precision to the second with no rounding that favours either party (`gmdate('H:i:s', ...)` on a seconds integer is fine; rounding to quarter hours is not), and no inference — when there is no assigned shift for the day, there is no basis to claim overtime, so the calculated value is null rather than zero or the whole worked span.

**Do not repurpose `extra_time`.** It is consumed today by the Resolución 38 DT reports in `app/Services/Reports/`, and quietly changing its meaning would change what those reports show a labor inspector. Introduce the calculated overtime as its own value (OHC in the PRD glossary) alongside it, and record the legal-limit version from KOL-36 that the day was judged against. A later task can retire `extra_time` once the DT reports have been re-pointed deliberately.

Behaviour to settle and record in the notes before finalising: how a shift that crosses midnight attributes its overtime (to the calendar day the shift started, per the usual Chilean reading), and what happens on a day with a scheduled shift but only one mark — the answer there is that OHC is not computed at all, because KOL-40 will flag it as an anomaly instead of guessing.

Reference: docs/PRD_Overtime_Module_Kolvi_EN.md sections 7.2 and 9.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Calculated overtime for a day is the positive difference between the last mark and the scheduled shift end, not the difference between worked span and scheduled duration
- [ ] #2 A day with no assigned shift produces no calculated overtime at all, rather than zero or the full worked span
- [ ] #3 A day with only one mark produces no calculated overtime, leaving the day to be flagged rather than guessed
- [ ] #4 Precision is to the second with no rounding at any intermediate step; rounding, if any, happens only when a report renders the value
- [ ] #5 A shift crossing midnight produces a correct value, and the calendar day it is attributed to is documented in the notes with its reasoning
- [ ] #6 The existing `extra_time` column and every Resolución 38 DT report that reads it produce exactly the same output as before this change
- [ ] #7 Each calculated day records which legal-limit version it was judged against
- [ ] #8 Pest tests cover a normal overflow, an early arrival with an on-time exit, a day with no shift, a day with one mark, an overnight shift, and a sub-minute overflow
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
