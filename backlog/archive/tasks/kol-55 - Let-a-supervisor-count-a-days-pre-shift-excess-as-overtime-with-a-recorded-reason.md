---
id: KOL-55
title: >-
  Let a supervisor count a day's pre-shift excess as overtime, with a recorded
  reason
status: To Do
assignee: []
created_date: '2026-08-07 19:00'
labels:
  - overtime
  - backend
  - frontend
  - domain
milestone: m-2
dependencies:
  - KOL-38
  - KOL-44
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 36000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
KOL-38 stores pre-shift excess (`shift start − first mark`) on every calculated day but only feeds it into OHC when the organization has enabled counting early arrival. That default is deliberate — see KOL-38 for the Art. 32 reasoning — but it creates a ceiling that needs an escape hatch.

**The problem.** The golden rule (PRD §7.1) makes the payable figure `MIN(OHR, OHA, OHC)`. If OHC excludes pre-shift excess, MIN caps everything at OHC, so a supervisor who wants to pay the warehouse crew for thirty minutes of legitimate loading before shift start structurally cannot. The hours are calculated, stored and visible in the system, and unpayable. For a company with mixed roles — office staff who simply arrive early, plus an opening crew that genuinely works — the tenant setting forces a choice between losing the crew's real hours and manufacturing phantom overtime for everyone else.

**What this delivers.** On a day whose pre-shift excess was excluded, an authorised reviewer can include it, for that day only, with a mandatory written reason. This is not an override of MIN: the reviewer is amending which stored excess feeds OHC for that day, after which MIN applies unchanged. That framing matters — it keeps KOL-46's invariants intact and keeps the act inside the module's philosophy that a human authorises, the system never assumes.

Consequences to honour:
- The inclusion is an explicit, logged human act, never a bulk default and never inferred from a pattern.
- Recomputation must not silently discard it — a later recalculation of the day preserves the inclusion and its reason, or surfaces the day for re-review, per KOL-46 AC #4.
- The reverse case (a tenant who counts early arrival, on a day where it was clearly a marking error) is covered by the existing downward path: KOL-44 AC #5 already lets an approver authorise fewer hours than calculated.

If usage shows reviewers including pre-shift excess repeatedly on the same shifts, that is the signal to promote the KOL-38 toggle from tenant level to shift level. This task is deliberately the cheaper answer first.

Reference: docs/PRD_Overtime_Module_Kolvi_EN.md §7.1, §7.2 and §7.5.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 On a day whose pre-shift excess was excluded by tenant policy, an authorised reviewer can include it in that day's OHC
- [ ] #2 Including it requires a written reason; the reason, the acting user and the timestamp are recorded on the day
- [ ] #3 After inclusion the payable figure is still MIN of the available inputs, computed against the amended OHC rather than bypassing the comparison
- [ ] #4 Inclusion is per day and never applies to a selection in bulk, so it cannot become a silent blanket policy
- [ ] #5 Recalculating a day that carries an inclusion preserves the inclusion and its reason, or surfaces the day for re-review rather than dropping it
- [ ] #6 A reviewer without the permission, or outside the employee's team scope, cannot include pre-shift excess
- [ ] #7 The action is available from the pending-overtime queue in Spanish, showing the pre-shift figure being included
- [ ] #8 Pest tests cover a successful inclusion and its effect on the final figure, inclusion without a reason being refused, a recalculation after inclusion, an unauthorised reviewer, and that inclusion is unavailable in bulk
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
- [ ] #5 vendor/bin/pint --dirty --format agent reports clean
- [ ] #6 sa test --compact passes
- [ ] #7 npm run types:check passes when TypeScript touched
- [ ] #8 Every PHP change has a Pest test
<!-- DOD:END -->
