---
id: KOL-46
title: Derive the final payable overtime figure as MIN of authorised and calculated
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-06 02:53'
updated_date: '2026-08-16 19:15'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-11
  - KOL-44
  - KOL-45
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 1100
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The golden rule from PRD section 7.1, taken directly from Talana flow: when a request, an authorisation and a calculation all exist, the payable figure is **MIN(OHR, OHA, OHC)**.

The reasoning is worth stating because it drives every edge case. If a worker requested 10 hours but worked 8, only 8 are paid — you cannot pay for hours nobody worked. If 10 were worked but only 8 authorised, only 8 are paid — the employer never agreed to the other 2. The minimum is the only figure that satisfies both constraints simultaneously, and Talana documentation describes exactly this cross-check of real hours against approval before transfer to payroll.

The fallback, for pure Mode B where no request exists: the figure is the calculated value capped by legal-cap validation, and **the record stays pending until a human confirms it**. It is never auto-approved on the grounds that there was nothing to compare it against.

What this task delivers is the derivation itself plus its invariants:
- Missing inputs are skipped, not treated as zero. A tenant with no request flow has no OHR, and that must not floor every figure to nothing.
- Recomputation is deterministic and cannot silently *raise* an already-approved figure. If the calculated value grows after approval — a corrected mark, a shift reassignment — the record is surfaced as needing re-review, per KOL-39, not quietly repriced upward.
- Hours that fall outside the final figure remain queryable as unauthorised, because KOL-13 and KOL-24 report on them and because a persistent gap between worked and authorised is the signal that something is wrong with marking practice or with the shift itself.

Precision stays at the second throughout, with no rounding at any intermediate step (Resolución 38 art. 44).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 When authorised and calculated hours both exist, the final payable figure is the lesser of the two
- [x] #2 Requested hours are recorded and reportable but never cap the payable figure: a day authorised for more than was requested, and worked in full, pays the authorised amount
- [x] #3 A missing input is excluded from the comparison rather than treated as zero, so a tenant with no request flow is not floored to nothing
- [x] #4 Under pure post-hoc mode with no request, the figure is the calculated value capped by legal-cap validation and the record remains pending until a human confirms it
- [x] #5 Recomputation is deterministic and can never silently raise an already-approved figure; a calculated value that grows after approval marks the record as needing re-review instead
- [x] #6 Hours falling outside the final figure remain queryable as unauthorised and are never merged into a payable total
- [x] #7 All arithmetic is to the second with no intermediate rounding
- [x] #8 Pest tests cover authorised exceeding worked, worked exceeding authorised, authorised exceeding requested with the full amount worked, each input missing in turn, the pure post-hoc fallback, and a post-approval recalculation that increases the calculated value
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Wire the decision into Workday: OvertimeAuthorization::approve() and object() stamp workday.overtime_decided_at/overtime_decided_value (= the frozen calculated_hours the decision was made against) after saving, via a new private stampWorkdayDecision() helper. This connects the KOL-39 re-review machinery (Workday::overtimeNeedsReReview()/scopeNeedsOvertimeReReview()), which exists but is currently never written by application code.
2. Add Pest coverage to tests/Feature/OvertimeAuthorizationTest.php:
   a. Authorised exceeding requested, worked in full -> pays the full authorised amount (OHR excluded from the MIN comparison) - AC#2.
   b. Missing-input matrix: OHC missing (no engine figure yet) -> final_hours equals the authorised amount in full, not floored to zero - AC#3.
   c. Named pure post-hoc fallback test (no OHR): record stays pending until a human confirms, and a cap breach demands justification - AC#4.
   d. Post-approval recalculation that raises OHC does not raise final_hours; the workday surfaces as overtimeNeedsReReview() instead - AC#5, exercises the new wiring.
3. Run sa test --compact against OvertimeAuthorizationTest, OvertimeQueueTest, CalculateOvertimeJobTest to confirm the wiring does not regress adjacent suites.
4. vendor/bin/pint --dirty --format agent.
5. Update docs/architecture.md line ~247 ('KOL-46 owns the remainder of the rule...') to describe what actually shipped.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implementation: MIN(OHA, OHC) arithmetic already existed in OvertimeAuthorization::finalHoursFor() (private, uses Duration::min() which already excludes missing inputs rather than flooring to zero) — AC#1/#2/#3/#6/#7 were effectively already satisfied by prior work (KOL-11/decision-1). The real gap was AC#5: Workday::overtime_decided_at/overtime_decided_value (built by KOL-39 specifically for this) were never written by application code, so Workday::overtimeNeedsReReview() was dead in production paths.

Change: added a private OvertimeAuthorization::stampWorkdayDecision(), called at the end of both approve() and object(), writing workday.overtime_decided_at = reviewed_at and workday.overtime_decided_value = the frozen calculated_hours the decision was made against. Since OvertimeAuthorization::openFor() never re-snapshots calculated_hours on an existing record (firstOrCreate) and WorkdayCalculator's write set deliberately excludes these two columns, an approved figure structurally cannot be silently raised by a later recalculation — it now surfaces via Workday::overtimeNeedsReReview() instead.

Added 4 Pest tests to tests/Feature/OvertimeAuthorizationTest.php: authorised > requested pays authorised in full (AC#2), missing OHC excluded not floored to zero (AC#3), named pure-post-hoc fallback with cap-justification requirement (AC#4), and post-approval recalculation raising OHC does not raise final_hours and flips overtimeNeedsReReview() to true (AC#5).

Verification: ./vendor/bin/sail artisan test --compact --filter=OvertimeAuthorizationTest -> 20/20 passed. Full suite: 1033/1042 passed; the 2 failures (OvertimeQueueTest query-count bound, UpcomingShiftsApiTest date horizon) are pre-existing on master, confirmed via git stash before this change — unrelated to KOL-46. vendor/bin/pint --dirty --format agent -> passed. docs/architecture.md's Overtime authorisation section updated to describe the MIN(OHA,OHC) amendment and the decision-stamp wiring, replacing the 'KOL-46 owns the remainder' placeholder.
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @jorge
created: 2026-08-08 15:27
---
Amended: the payable figure is MIN(OHA, OHC), not MIN(OHR, OHA, OHC).

The three-term rule inherited from Talana fails a real case: the worker requests 1h, the supervisor authorises 3h, the worker works 3h. MIN pays 1h, leaving two hours unpaid that were both explicitly authorised by the employer and actually worked — against art. 32 and the DT reality criterion. See decision-1.

Note that this task's own justification never needed the third term: 'requested 10 but worked 8, pay 8' is OHC capping, and 'worked 10 but authorised 8, pay 8' is OHA capping. OHR only ever binds when it is below both others, which is precisely the underpayment case.

The alternative fix — forbidding a supervisor from authorising more than was requested — was rejected because it recreates the same trap one step earlier: the supervisor who needs 3h from someone who asked for 1h could not authorise them, and the extra hours would be unpayable again.

OHR stays on the record as evidence and traceability (who asked for what, and when, per KOL-11 and KOL-51) and as the Mode A gate for whether there is anything to authorise at all. It leaves the arithmetic.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
MIN(OHA, OHC) arithmetic already existed from KOL-11 (per decision-1's amendment dropping OHR from the comparison); the real gap was wiring OvertimeAuthorization::approve()/object() to stamp Workday::overtime_decided_at/overtime_decided_value (built by KOL-39, never written) via a new stampWorkdayDecision(), so a post-approval recalculation that raises OHC surfaces as overtimeNeedsReReview() instead of silently repricing. Added 4 Pest tests covering OHR-ignored, missing-OHC, the pure post-hoc fallback, and post-approval recalculation. Verified: OvertimeAuthorizationTest 20/20 pass; full suite 1033/1042 pass (2 pre-existing failures on master, unrelated); pint clean. docs/architecture.md updated.
<!-- SECTION:FINAL_SUMMARY:END -->
