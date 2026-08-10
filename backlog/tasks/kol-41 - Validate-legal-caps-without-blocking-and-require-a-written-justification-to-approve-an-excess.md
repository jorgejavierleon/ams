---
id: KOL-41
title: >-
  Validate legal caps without blocking, and require a written justification to
  approve an excess
status: Done
assignee: []
created_date: '2026-08-06 02:51'
updated_date: '2026-08-10 23:36'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-39
  - KOL-36
  - KOL-11
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 600
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 7.3. This answers the question KOL-11 left open as acceptance criterion 5, and the answer is **surface, do not block** — with a price attached.

Resolución 38 art. 45.2 is unambiguous that a legal-cap alert is advisory and never blocks the entry. GeoVictoria confirms the same operational reading from the market side: the platform allows authorising more than 2 hours in a day for exceptional cases such as critical-service continuity, showing a warning rather than refusing. A system that hard-blocks at 2 hours simply pushes the client into recording the truth somewhere Kolvi cannot see, which is worse for them in an audit than an authorised excess with a reason attached.

So: the caps from KOL-36 are validated at approval time, the excess is allowed, and **approving beyond a cap requires a written justification from the approver**. No justification, no approval. That justification is what makes the excess defensible later.

Caps to validate, all resolved for the date being approved rather than for today:
- 2 overtime hours per day (Código del Trabajo art. 31)
- 12 overtime hours per week
- ordinary plus extraordinary within the daily and weekly ceilings

Weekly caps mean this validation is not per-day in isolation: approving 2 hours on Friday can push the week over its limit even though that Friday is individually within bounds. The check therefore needs the week context, and the week the day belongs to has to be defined explicitly — write the rule and its reasoning into the notes, including what happens to a week straddling a month boundary, since KOL-24 will depend on the same definition.

Warning at the point of *entry* (loading a schedule that would exceed the caps) is also required by art. 45.2, and is likewise non-blocking.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The daily overtime cap, the weekly overtime cap and the combined ordinary-plus-extraordinary ceilings are evaluated at approval time using the limits in force on the date being approved
- [x] #2 Exceeding a cap never prevents saving a mark, saving a shift or calculating a day, per Resolución 38 art. 45.2
- [x] #3 Approving hours beyond a cap is possible, but only when the approver supplies a written justification; approval without one is refused
- [x] #4 The justification is stored against the approval and is retrievable for audit alongside who approved and when
- [x] #5 The weekly evaluation accounts for hours already approved earlier in the same week, so an individually valid day that pushes the week over its limit is caught
- [x] #6 The definition of the week used, and the handling of a week straddling a period boundary, is documented in the notes with its reasoning
- [x] #7 Loading a schedule that would exceed the caps produces an on-screen advisory warning that does not block the save
- [x] #8 Pest tests cover a day within caps, a day over the daily cap, a week pushed over by an individually valid day, approval rejected for a missing justification, approval accepted with one, and a cap change between the worked date and the approval date
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
1. Add App\Services\Overtime\OvertimeCapBreach (readonly value object: dailyOvertime, weeklyOvertime, dailyTotal, weeklyTotal bools + any()/reasons()).
2. Add App\Services\Overtime\OvertimeCapEvaluator: evaluate(OvertimeAuthorization $authorization) reads the day's proposed payable overtime via $authorization->authorizedOvertime() (already forceFill'd pre-save), resolves LegalHourLimits::on()/forWeekOf() against $authorization->date (the worked date, never today), computes ordinary hours as workday.worked_time - workday.calculated_overtime, sums other Approved authorizations in the same Mon-Sun week (via LegalHourLimits::weekStart(), excluding self) for the weekly overtime and weekly total checks.
3. Wire into OvertimeAuthorization::booted() saving hook, after the existing anomaly-flag check: when status is Approved and the evaluator reports any cap breached, require a non-blank reason or throw a new OvertimeDecisionRefused::withoutJustification($breach).
4. ShiftController::assertWithinLegalHours: remove the blocking ValidationException on exceeding ordinary_weekly_hours (KOL-36 left this blocking; Res. 38 art. 45.2 requires advisory-only). Keep the negative-duration guard. The client already renders a non-blocking amber warning in shift-form.tsx from the same maxWeeklyHours/maxDailyHours props, so AC #7 needs no new frontend work. Drop the now-orphaned lang key ui.shifts.validation.exceeds_weekly (en/es).
5. Update tests/Feature/ShiftManagementTest.php: the two tests asserting a blocked save on exceeding the weekly cap become tests asserting the shift saves anyway (advisory only), keeping the negative-hours rejection test intact.
6. Update tests/Feature/OvertimeAuthorizationTest.php: the "fully authorised day" test approves 3h against a 2h daily cap on 2026-08-03 - add a reason so it keeps passing under the new rule.
7. Add new Pest coverage (new file tests/Feature/OvertimeCapValidationTest.php) for AC #8: day within caps approves without a reason; a day over the daily overtime cap is refused without a reason and accepted with one; a week pushed over the weekly overtime cap by an individually-valid day is refused without a reason; a day breaching the combined ordinary+extraordinary ceiling is refused without a reason; a cap version change between the worked date and the approval date is judged by the worked date's version, not today's.
8. AC #6 (week definition + boundary handling) is already documented in KOL-36's LegalHourLimits::forWeekOf() docblock and in this task's own Implementation Notes - no new doc needed.
9. Run vendor/bin/pint --dirty --format agent, then sa test --compact on the touched suites.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Week definition settled by KOL-36 and already implemented there — do not re-decide it. A week is Monday–Sunday (matching DailyReportService, the DT-certified report), and a week straddling a limit change is judged against the version in force on its **Monday**. Reasoning: two of the three Ley 21.561 steps land mid-week (26 Apr 2024 was a Friday, 26 Apr 2028 is a Wednesday), and the weekly cap is a budget spent across the week — applying a newly lowered ceiling from Wednesday would retroactively turn hours already lawfully worked on Monday and Tuesday into an excess against a limit that did not exist when they were worked. Use App\\Services\\LegalHourLimits::forWeekOf() for the weekly caps and ::on() for the daily ones. This answers AC #6 of this task; KOL-24 must use the same definition. Also note KOL-36 left shift-cap validation blocking as it was: making it advisory per Res. 38 art. 45.2 is AC #2/#7 here.

Implementation complete.

- app/Services/Overtime/OvertimeCapBreach.php + OvertimeCapEvaluator.php: evaluates the daily/weekly overtime caps and the combined ordinary+extraordinary daily/weekly ceilings for a proposed approval, resolved via LegalHourLimits against the worked date (never "today"). Uses OvertimeAuthorization::authorizedOvertime() (the payable MIN-of-authorised-and-calculated figure) as the proposed daily figure, so a request for more hours than were worked never trips a cap that was never going to be owed. Weekly sums exclude the record being decided and read every other Approved authorisation in the same Mon-Sun week (LegalHourLimits::weekStart()).
- OvertimeAuthorization::booted(): the saving hook now requires a non-blank reason whenever the evaluator reports any breach, refusing via a new OvertimeDecisionRefused::withoutJustification(). The excess itself is never blocked - only an unexplained approval is.
- ShiftController: removed the blocking ValidationException on exceeding the ordinary weekly cap (assertWithinLegalHours -> assertNoNegativeDurations). The client already renders the same warning live from maxWeeklyHours/maxDailyHours in shift-form.tsx, so AC #7 needed no frontend change. Dropped the now-orphaned ui.shifts.validation.exceeds_weekly lang key (en/es).
- Updated tests/Feature/ShiftManagementTest.php (blocked-save tests -> allowed-but-advisory) and tests/Feature/OvertimeAuthorizationTest.php (one pre-existing approve() call now needs a reason since it exceeds the daily cap). Added tests/Feature/OvertimeCapValidationTest.php covering AC #8: within caps, over the daily overtime cap (refused then accepted with a reason), a week pushed over by an individually-valid day, the combined daily ceiling in isolation, and a cap version change between the worked date and the approval date.
- Full suite: 922 passed, 4 pre-existing skips, 0 failures. vendor/bin/pint clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Legal caps are now validated at approval time (daily/weekly overtime, combined ordinary+extraordinary daily/weekly) via App\Services\Overtime\OvertimeCapEvaluator, always resolved against the worked date. Approving beyond any cap requires a non-blank reason, enforced structurally in OvertimeAuthorization's saving hook via a new OvertimeDecisionRefused::withoutJustification(). ShiftController no longer blocks saving a shift over the ordinary weekly cap (Res. 38 art. 45.2 is advisory-only); the existing client-side warning in shift-form.tsx already surfaces the excess without a server round trip. Verified with 6 new/updated Pest tests (tests/Feature/OvertimeCapValidationTest.php + updates to OvertimeAuthorizationTest.php and ShiftManagementTest.php) covering every AC #8 scenario; full suite 922 passed / 4 pre-existing skips / 0 failures; pint clean.
<!-- SECTION:FINAL_SUMMARY:END -->
