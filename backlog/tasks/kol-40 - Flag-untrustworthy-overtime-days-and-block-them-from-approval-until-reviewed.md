---
id: KOL-40
title: Flag untrustworthy overtime days and block them from approval until reviewed
status: Done
assignee: []
created_date: '2026-08-06 02:50'
updated_date: '2026-08-09 19:24'
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
- [x] #1 A day is flagged when the employee has no assigned shift, when only one of the two marks exists, when the contract is not active on that date, when a mark fell outside the geofence, and when the period volume exceeds the tenant threshold
- [x] #2 Each flag carries a machine-readable reason and a Spanish human-readable explanation a supervisor can act on
- [x] #3 A flagged day cannot reach approved status through any path until a human has reviewed it
- [x] #4 Flagging never blocks saving a mark, saving a shift or running the calculation, per Resolución 38 art. 45.2
- [x] #5 Flags are recomputed by the calculation engine, so a flag whose cause was corrected disappears without manual intervention
- [x] #6 The existing `WorkdayStatus` values are reused for the missing-shift and incomplete-day conditions rather than the condition being re-derived separately
- [x] #7 The volume threshold read is the tenant one from KOL-37, not a constant
- [x] #8 Pest tests cover each flag condition individually, a day with several flags at once, a flag clearing after the mark is corrected, and the fact that a flagged day cannot be approved
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Migration: add nullable json `anomaly_flags` column to `workdays` (after `overtime_calculated_at`).
2. New enum App\Enums\AnomalyFlagReason (NoAssignedShift, IncompleteMarks, ContractNotActive, OutsideGeofence, PeriodVolumeExceeded) with label() via lang keys ui.overtime.anomaly_reasons.* (es + en).
3. OrganizationSettings::overtimeWeeklyAnomalyThresholdHours() accessor mirroring overtimeCountsPreShiftExcess().
4. WorkdayCalculator: inject OrganizationSettings; extend getWorkdayQuery() select with users.contract_start_date/contract_end_date and mark_in/mark_out geo_status; add a batched weekly-other-days-seconds helper (SUM(TIME_TO_SEC(calculated_overtime)) grouped by user for the ISO week, excluding the date being computed) used by both runForDate's chunk and recalculateWorkday; add calculateAnomalyFlags() producing the 5 reasons (first two reuse WorkdayStatus::Irregular/Incomplete per AC#6, geofence reuses existing geo_status verdict, contract compares row date to contract_start/end_date, period volume compares weekly total incl. this day's own OHC against the tenant threshold); wire into calculatedAttributes() as anomaly_flags (json-encoded array or null), which the existing upsert column-diff logic picks up automatically.
5. Workday model: cast anomaly_flags => array; add anomalyFlags(): array<AnomalyFlagReason> and isFlagged(): bool helpers.
6. OvertimeAuthorization::booted() saving hook: when status is being set to Approved and $this->workday->isFlagged(), throw OvertimeDecisionRefused::withUnresolvedAnomalies($reasons) (Objected/Pending stay unaffected, so a flagged day can still be objected).
7. OvertimeDecisionRefused: add withUnresolvedAnomalies(array $reasons) factory, same structural pattern as withoutAReviewer().
8. Pest tests: new tests/Feature/WorkdayAnomalyFlagsTest.php covering each of the 5 conditions individually, a day with several flags at once, and a flag clearing after recalculateWorkday() following a mark correction; extend tests/Feature/OvertimeAuthorizationTest.php with a case asserting approve() throws on a flagged day's authorization while object() still succeeds.
9. vendor/bin/pint --dirty --format agent, then sa test --compact.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented: AnomalyFlagReason enum (5 reasons), anomaly_flags json column on workdays computed in WorkdayCalculator::calculatedAttributes() (self-clearing on recalculation), OrganizationSettings::overtimeWeeklyAnomalyThresholdHours() accessor, OvertimeAuthorization::booted() now refuses Approved while the workday isFlagged() (Objected stays reachable). New tests/Feature/WorkdayAnomalyFlagsTest.php (14 tests) covers each condition individually, multiple flags at once, self-clearing after correction, non-blocking saves, and the approval block. Full targeted overtime suite (97 tests) passes; full project suite running to confirm no wider regressions.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added AnomalyFlagReason (5 PRD §7.4 conditions) computed by WorkdayCalculator on every pass and stored on Workday.anomaly_flags (json), self-clearing via the existing upsert/recalculation path. OvertimeAuthorization::booted() now refuses Approved while the workday isFlagged(), leaving Objected reachable. Verified with tests/Feature/WorkdayAnomalyFlagsTest.php (14 tests: each condition individually, non-flagging edge cases, multiple flags at once, self-clearing after correction, non-blocking saves per art. 45.2, approval block). Full suite: 918 passed, 4 pre-existing skips, 0 failed. Pint clean. No TypeScript touched.
<!-- SECTION:FINAL_SUMMARY:END -->
