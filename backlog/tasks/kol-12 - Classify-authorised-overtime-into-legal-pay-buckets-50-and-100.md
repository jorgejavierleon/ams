---
id: KOL-12
title: Classify authorised overtime into legal pay buckets (50% and 100%)
status: Done
assignee: []
created_date: '2026-08-04 11:11'
updated_date: '2026-08-23 21:34'
labels:
  - payroll-reports
  - backend
  - domain
milestone: m-0
dependencies:
  - KOL-11
  - KOL-46
  - KOL-49
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 11000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-1 requires the payroll summary to break overtime into 'HHEE 50%, HHEE 100% según corresponda'. Today `workdays.extra_time` is one undifferentiated time column — there is no bucketing anywhere in the codebase.

In Chile the recargo on horas extraordinarias is 50% over the ordinary hourly rate (Código del Trabajo art. 32). The '100%' bucket clients ask for is not a second overtime rate — it is how many payroll systems encode work performed on a Sunday or festivo for workers whose weekly rest falls there (art. 38), which carries its own compensation rules. **Confirm this reading before implementing**; if the correct modelling is 'domingo/festivo trabajado' as a distinct concept rather than an overtime percentage, model it that way and name it accordingly. The point is that the payroll summary must expose the buckets an accountant needs, not that there must literally be a field called 100%.

The inputs already exist: `workdays` carries the date, worked and extra time; `app/Models/Holiday.php` and the seeder cover festivos; `app/Services/Reports/SundaysReportService.php` already reasons about Sunday work for the DT report and is worth reading before writing anything new. KOL-11 supplies which hours are authorised.

Produce a service that, for an employee over a date range, returns the classified breakdown. It must not mutate `workdays` — this is a read-side derivation, so a recalculation of attendance never silently changes historical payroll figures.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A service returns, for an employee and date range, overtime split into its pay buckets plus an unauthorised bucket, derived from workdays without writing to them
- [x] #2 Sunday and holiday work is identified using the existing Holiday data and produces the correct bucket for the reading confirmed in the description
- [x] #3 The interpretation of the 50/100 buckets is confirmed against Código del Trabajo art. 32 and 38 and written into the task notes before the implementation is finalised
- [x] #4 Only hours authorised per KOL-11 land in a payable bucket; unauthorised excess is reported separately and never merged into a payable total
- [x] #5 The breakdown totals reconcile exactly with the sum of extra_time over the period, so nothing is lost or double-counted
- [x] #6 Recalculating a workday after the fact does not alter a previously exported classification silently; the behaviour on recalculation is tested
- [x] #7 Pest tests cover a normal weekday, a Sunday, a holiday, a partially authorised day, and a fully unauthorised day
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
1. App\Enums\OvertimePayBucket (OrdinaryDay|SundayOrHoliday) + es/en labels under ui.overtime.pay_buckets. Legal reading (art. 32/38, to record in task notes): Chile's recargo is a uniform 50% on ALL overtime (art. 32) -- there is no statutory 100% overtime rate. The "100%" bucket clients ask for is payroll shorthand for routing Sunday/holiday overtime (art. 38 rest-day territory) to its own Nubox/haber code, not a second legal percentage. So bucket by day type, name accurately, let the export template (future KOL-25/26) map each bucket to whatever code the client configures.
2. App\Services\Overtime\OvertimePayBucketBreakdown: readonly DTO (userId, ordinaryDayHours, sundayOrHolidayHours, compensatedInRestDaysHours, unauthorizedHours -- all Duration). The 4th bucket exists because a still-active (unexpired) rest-day-compensated day is authorized but not money-payable and not "unauthorized" either -- without it AC#5's reconciliation silently loses hours the moment KOL-47 compensation is in play.
3. App\Services\Overtime\OvertimePayBucketClassifier::forPeriod(start, end, userIds): Collection<int, Breakdown> keyed by user_id:
   a. Payable buckets: OvertimeExportDataset::forPeriod() lines, bucketed by dayType (Weekday -> ordinaryDay, Sunday|Holiday -> sundayOrHoliday), summed in seconds.
   b. Unauthorized bucket: Workday::whereIn(user_id)->whereBetween(date, period)->whereNotNull(calculated_overtime)->with('overtimeAuthorization')->get(), sum unauthorizedOvertime() per user (handles the "no row opened" case via Workday's own null-safe method).
   c. Compensated-in-rest-days bucket: from the same Workday set, sum calculated_overtime for days whose overtimeAuthorization isApproved() && compensation_type===RestDays && its restDayBalance is not yet expired (or has none swept) -- i.e. authorized hours neither in (a) nor money-owed yet.
   d. Emit one Breakdown per requested userId, zeroed if absent.
4. Pest tests tests/Feature/OvertimePayBucketClassifierTest.php: a normal weekday (AC#7), a Sunday, a holiday, a partially authorized day (some unauthorized remainder), a fully unauthorized day (no authorization row at all), the rest-day-compensated-active case reconciling into its own bucket, org scoping, and the 4-bucket sum equalling calculated_overtime for a mixed period (AC#5, using calculated_overtime not extra_time -- deviation recorded in task notes per KOL-49/KOL-46 precedent since extra_time is the legacy DT-report figure, not the OHC this module derives from).
5. vendor/bin/pint --dirty --format agent, then sa test --compact --filter=OvertimePayBucketClassifierTest, then the adjacent overtime suites to confirm no regressions.
6. Record the confirmed 50%/100% legal reading in task notes (AC#3) before closing.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
AC#3 confirmed reading: Código del Trabajo art. 32 sets a single, uniform 50% recargo on every overtime hour -- there is no statutory second (100%) overtime rate anywhere in the Code. The "HHEE 100%" field clients and competing payroll tools (Talana/Buk/GeoVictoria) ask for is payroll shorthand for routing Sunday/holiday overtime to its own concept/haber code, because that day also touches art. 38's weekly-rest rules -- it is not a distinct legal percentage. Modelled accordingly: App\Enums\OvertimePayBucket has cases OrdinaryDay/SundayOrHoliday (day-type buckets), not a literal "50%"/"100%" pair. An export template (future KOL-25/26) is where each bucket gets mapped to whatever haber/descuento code the client configures.

AC#5 deviation recorded: reconciles against calculated_overtime (OHC, KOL-38's shift-excess figure), not workdays.extra_time (the legacy Resolución-38-only figure the original task text named) -- consistent with this task's own m-2 rescoping comment to read KOL-49's dataset and KOL-46's arithmetic rather than workdays.extra_time directly, since the two columns are not the same measure and can diverge.

Four buckets, not two: added compensatedInRestDaysHours (KOL-47) alongside the two payable buckets and unauthorizedHours, because an active (unexpired) rest-day-compensated day is authorised but neither money-payable now nor "unauthorised" -- without a third disposition the reconciliation in AC#5 would silently lose those hours the moment KOL-47 compensation is in play. Documented in docs/architecture.md.

Known scope boundary: an expired rest-day balance's payable line (from OvertimeExportDataset) is dated by its expiry_date, which under art. 32 section 4's six-month window can fall in a different period than the workday that earned it. That inflow lands in whichever period's classification the expiry_date falls into and is not expected to reconcile against that period's own calculated_overtime sum -- it is an intentional cross-period addition, same as KOL-49's own design. The reconciliation test (AC#5) exercises a period with no expired balances to keep this observable and unambiguous.

Verification: ./vendor/bin/sail artisan test --compact --filter=OvertimePayBucketClassifierTest -> 9/9 passed, 24 assertions. Adjacent suites (OvertimeAuthorizationTest, OvertimeExportDatasetTest, OvertimeRestDayBalanceTest, OvertimeQueueTest, WorkdayOvertimeTest) re-run together: 62/62 passed, no regressions. vendor/bin/pint --dirty --format agent: clean. phpstan analyse (via sail php, since local PHP is 8.4 and the project targets 8.5) on the three new files: no errors. No TypeScript touched, so DoD#3 does not apply. docs/architecture.md updated with a new "Pay-bucket classification (KOL-12)" section.
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @planning
created: 2026-08-06 02:56
---
Scope note from the overtime module planning (m-2): KOL-11 has been rescoped to just the authorisation record, and the wider overtime work now lives in milestone m-2 "Módulo de Horas Extras (HHEE)". This task should read the approved-only export dataset from KOL-49 rather than reaching into overtime records or `workdays.extra_time` directly — KOL-49 already resolves day type (weekday / Sunday / holiday) from the existing Holiday data and the SundaysReportService reasoning, and already excludes rest-day-compensated and unauthorised hours. The unauthorised bucket this task must report separately comes from KOL-46.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Built App\Services\Overtime\OvertimePayBucketClassifier::forPeriod(), sorting a period's overtime into 4 buckets per employee: ordinaryDayHours/sundayOrHolidayHours (payable, read from KOL-49's OvertimeExportDataset, bucketed by App\Enums\OvertimePayBucket per the confirmed reading that Chilean law sets one uniform 50% recargo — the '100%' field is day-type routing, not a second rate), compensatedInRestDaysHours (KOL-47, authorised but paid in time off), and unauthorizedHours (KOL-46). Verified with 9 new Pest tests (24 assertions) covering weekday/Sunday/holiday routing, partial and full non-authorisation, rest-day compensation, org scoping, exact 4-bucket reconciliation against calculated_overtime, and that a post-approval recalculation cannot silently move an already-classified payable figure. Adjacent overtime suites (62 tests) and the full project suite (1127/1134, 7 pre-existing skips) re-run with no regressions. Pint and phpstan clean. docs/architecture.md documents the reasoning and deviations.
<!-- SECTION:FINAL_SUMMARY:END -->
