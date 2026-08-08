---
id: KOL-46
title: Derive the final payable overtime figure as MIN of authorised and calculated
status: To Do
assignee: []
created_date: '2026-08-06 02:53'
updated_date: '2026-08-08 15:27'
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
- [ ] #1 When authorised and calculated hours both exist, the final payable figure is the lesser of the two
- [ ] #2 Requested hours are recorded and reportable but never cap the payable figure: a day authorised for more than was requested, and worked in full, pays the authorised amount
- [ ] #3 A missing input is excluded from the comparison rather than treated as zero, so a tenant with no request flow is not floored to nothing
- [ ] #4 Under pure post-hoc mode with no request, the figure is the calculated value capped by legal-cap validation and the record remains pending until a human confirms it
- [ ] #5 Recomputation is deterministic and can never silently raise an already-approved figure; a calculated value that grows after approval marks the record as needing re-review instead
- [ ] #6 Hours falling outside the final figure remain queryable as unauthorised and are never merged into a payable total
- [ ] #7 All arithmetic is to the second with no intermediate rounding
- [ ] #8 Pest tests cover authorised exceeding worked, worked exceeding authorised, authorised exceeding requested with the full amount worked, each input missing in turn, the pure post-hoc fallback, and a post-approval recalculation that increases the calculated value
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
Amended: the payable figure is MIN(OHA, OHC), not MIN(OHR, OHA, OHC).

The three-term rule inherited from Talana fails a real case: the worker requests 1h, the supervisor authorises 3h, the worker works 3h. MIN pays 1h, leaving two hours unpaid that were both explicitly authorised by the employer and actually worked — against art. 32 and the DT reality criterion. See decision-1.

Note that this task's own justification never needed the third term: 'requested 10 but worked 8, pay 8' is OHC capping, and 'worked 10 but authorised 8, pay 8' is OHA capping. OHR only ever binds when it is below both others, which is precisely the underpayment case.

The alternative fix — forbidding a supervisor from authorising more than was requested — was rejected because it recreates the same trap one step earlier: the supervisor who needs 3h from someone who asked for 1h could not authorise them, and the extra hours would be unpayable again.

OHR stays on the record as evidence and traceability (who asked for what, and when, per KOL-11 and KOL-51) and as the Mode A gate for whether there is anything to authorise at all. It leaves the arithmetic.
---
<!-- COMMENTS:END -->
