---
id: KOL-46
title: >-
  Derive the final payable overtime figure as MIN of requested, authorised and
  calculated
status: To Do
assignee: []
created_date: '2026-08-06 02:53'
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
- [ ] #1 When requested, authorised and calculated hours all exist, the final figure is the minimum of the three
- [ ] #2 A missing input is excluded from the comparison rather than treated as zero, so a tenant with no request flow is not floored to nothing
- [ ] #3 Under pure post-hoc mode with no request, the figure is the calculated value capped by legal-cap validation and the record remains pending until a human confirms it
- [ ] #4 Recomputation is deterministic and can never silently raise an already-approved figure; a calculated value that grows after approval marks the record as needing re-review instead
- [ ] #5 Hours falling outside the final figure remain queryable as unauthorised and are never merged into a payable total
- [ ] #6 All arithmetic is to the second with no intermediate rounding
- [ ] #7 Pest tests cover all three inputs present, each input missing in turn, requested exceeding worked, worked exceeding authorised, the pure post-hoc fallback, and a post-approval recalculation that increases the calculated value
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
