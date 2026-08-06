---
id: KOL-39
title: >-
  Run the overtime calculation as an idempotent queued job that can never emit
  an approved figure
status: To Do
assignee: []
created_date: '2026-08-06 02:50'
updated_date: '2026-08-06 02:56'
labels:
  - overtime
  - backend
  - domain
milestone: m-2
dependencies:
  - KOL-38
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 400
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The calculation engine must be able to run, re-run and be trusted, and it must be structurally incapable of producing a payable figure on its own. PRD section 7.2: *"Never writes directly to an approved state. The output of this calculation can reach pending review at most."*

That sentence is the whole point of this task. It is not a UI convention or a code-review habit — the engine has no write path to `approved`, so no future refactor, backfill or console command can create a payable hour without a human decision behind it.

What lands here:
- The engine runs as a queued job after shift close-out, per organization and date, and can be re-run for a date range without duplicating or double-counting.
- Re-running is idempotent: the same inputs produce the same record, and a changed input (a corrected mark, a newly approved leave, a reassigned shift) updates the calculated value while leaving any human decision already attached to that day intact and visibly stale.
- The record links back to the raw marks it was derived from, so the traceability chain in KOL-49 has something to walk.

Kolvi already has the row the PRD calls `WorkdayCalculation`: `app/Models/Workday.php`, produced by `WorkdayCalculator`. Extend that rather than introducing a parallel daily table — a second row per employee per day would immediately drift out of sync with the one the DT reports read. The state machine belongs on the separate authorisation record in KOL-11, not on the workday.

Recalculation already has a trigger pattern worth following: `app/Observers/LeaveObserver.php` fires workday recalculation on leave status or range changes, and `WorkdayCalculator::recalculateWorkday()` recomputes a single row in place.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Calculated overtime is produced by a queued job for an organization and date, and the same job can be run over a date range for backfill
- [ ] #2 There is no code path by which the calculation engine can write an approved or payable state; the highest state it can produce is pending review
- [ ] #3 Re-running the job for a date already processed updates the calculated value rather than creating a second record, and produces an identical result when inputs are unchanged
- [ ] #4 A corrected mark, a newly approved leave or a changed shift assignment causes the affected day to be recalculated
- [ ] #5 When a day already carries a human decision and its calculated value changes, the decision is preserved and the record is surfaced as needing re-review rather than being silently overwritten or silently kept
- [ ] #6 The calculated record can be traced back to the specific marks it was derived from
- [ ] #7 The job is organization-scoped and processing one tenant never touches another
- [ ] #8 Pest tests cover a first run, an idempotent re-run, a re-run after a mark correction, a re-run of a day already decided, and tenant isolation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
