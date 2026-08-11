---
id: KOL-60
title: >-
  Exclude a day's excess from overtime when it compensates a written, authorised
  permiso
status: To Do
assignee: []
created_date: '2026-08-10 23:56'
labels:
  - overtime
  - backend
  - frontend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-38
  - KOL-44
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 41000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Código del Trabajo art. 32, final paragraph: "No serán horas extraordinarias las trabajadas en compensación de un permiso, siempre que dicha compensación haya sido solicitada por escrito por el trabajador y autorizada por el empleador." Hours worked to make up for a previously granted permiso are not overtime at all — not payable as OHC/HHEE, not counted against the legal caps — provided the make-up was requested in writing by the employee and authorised by the employer. This paragraph is not covered anywhere in the PRD or in any existing overtime ticket: KOL-38 computes pre/post-shift excess unconditionally whenever marks exceed the shift, and today that excess has no way to be recorded as anything other than ordinary or overtime work. Without this, the system either inflates OHC with hours the law says are not extraordinary, or requires an off-system workaround with no audit trail.

**What this delivers.** An authorised reviewer can mark a day of calculated excess as compensating an employee permiso instead of routing it through the overtime pipeline. The mark requires both legal conditions to be recorded, not assumed: the employee written request for the compensation, and the employer authorisation act, each attributable to a person and a timestamp. Once marked:
- the day excess is excluded from OHC/HHEE and therefore from the payable figure (KOL-46 MIN rule) — it never becomes an OvertimeAuthorization pending approval,
- the excluded hours do not count toward the daily/weekly legal overtime caps (KOL-41),
- the excluded hours never appear as extraordinary hours in overtime reports or exports (KOL-49/KOL-50).

This is the mirror image of KOL-55 (which lets a reviewer *include* excluded pre-shift excess into OHC): here a reviewer *excludes* excess that would otherwise be eligible, for a distinct legal reason, and the two mechanisms should not be confused or merged.

Open question for whoever picks this up: whether the "permiso" being compensated must reference an existing Leave record (app/Models/Leave.php) or can be a free-standing written request — Leave today is date-range/business-day oriented, not hour-level, so research the closest fit before implementing rather than assuming.

Reference: Código del Trabajo art. 32 (final paragraph, not currently cited in docs/PRD_Overtime_Module_Kolvi_EN.md). See also KOL-38 (calculated excess), KOL-46 (MIN payable rule), KOL-41 (legal caps), KOL-55 (the inclusion mirror case).

## User stories for manual testing (Gherkin)

```gherkin
Feature: Exclude permiso make-up hours from overtime

  Scenario: Marking a day as permiso compensation excludes its excess from overtime
    Given an employee has a calculated post-shift excess on a worked day
    And the employee submitted a written request to compensate a permiso granted on an earlier date
    When an authorised reviewer marks the day as permiso compensation and records the authorisation
    Then the day excess is excluded from OHC/HHEE
    And the day does not appear in the pending-overtime queue awaiting approval
    And the day does not appear in the overtime export as extraordinary hours

  Scenario: Marking is refused without a written request from the employee
    Given an employee has a calculated excess on a day
    When a reviewer attempts to mark it as permiso compensation without a written request reference
    Then the action is refused
    And the day excess remains eligible for ordinary overtime approval

  Scenario: Marking is refused without an employer authorisation
    Given an employee has a written request to compensate a permiso
    But no employer authorisation has been recorded
    When a reviewer attempts to mark the day as permiso compensation
    Then the action is refused

  Scenario: Excluded hours do not count toward the legal overtime caps
    Given an employee has approved overtime earlier in the week that is close to the weekly cap
    And a further block of excess on a later day is marked as permiso compensation
    When the weekly overtime cap is evaluated for that week
    Then the hours marked as permiso compensation are not included in the weekly total

  Scenario: A reviewer outside the employee team scope cannot mark the day
    Given a supervisor who does not manage the employee
    When they attempt to mark the day excess as permiso compensation
    Then the action is refused
```
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 An authorised reviewer can mark a day's calculated excess (pre-shift and/or post-shift) as compensating an employee's permiso instead of routing it as overtime
- [ ] #2 Marking requires recording both legal conditions: the employee's written request for the compensation and the employer's authorisation, each attributed to a person and a timestamp; missing either refuses the action
- [ ] #3 A day marked as permiso compensation has its excess excluded from OHC/HHEE and from the payable overtime figure (KOL-46 MIN rule), and never becomes a pending OvertimeAuthorization
- [ ] #4 Hours excluded as permiso compensation do not count toward the daily or weekly legal overtime caps (KOL-41)
- [ ] #5 Hours excluded as permiso compensation never appear as extraordinary hours in overtime reports or exports (KOL-49, KOL-50)
- [ ] #6 Marking is per day and cannot be applied in bulk, so it cannot become a blanket policy hiding real overtime
- [ ] #7 A day already carrying an approved OvertimeAuthorization cannot be marked as permiso compensation without first reversing or objecting the approval
- [ ] #8 Recalculating a day that carries a permiso-compensation mark preserves the mark, its written request and its authorisation, or surfaces the day for re-review rather than silently discarding it
- [ ] #9 A reviewer without the permission, or outside the employee's team scope, cannot mark a day as permiso compensation
- [ ] #10 The mark, the written request and who authorised it are visible in Spanish from the pending-overtime queue or the day detail
- [ ] #11 Pest tests cover: marking excludes the excess from OHC/HHEE, marking refused without a written request, marking refused without authorisation, excluded hours excluded from legal cap totals, recalculation preserves the mark, an unauthorised or out-of-scope reviewer is refused, bulk marking is unavailable, and a day with an existing approved authorisation cannot be marked without reversal
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
