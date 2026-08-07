---
id: KOL-36
title: >-
  Model the legal working-hour limits as date-versioned values that keep the
  past stable
status: Done
assignee: []
created_date: '2026-08-06 02:48'
updated_date: '2026-08-07 01:38'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies: []
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: task
ordinal: 100
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Every legal limit this module validates against is currently either hardcoded or wrong. `config/ams.php` declares `max_weekly_hours => 45` and `max_daily_hours => 10` — the 45 has been stale since Ley 21.561 took the ordinary workweek to 42 hours on 26 April 2026, and it keeps falling to 40 by 2028.

This task is the domain: the versioned limits themselves, the resolver, and the guarantees that keep the past stable. The SaaS admin screen for maintaining them is KOL-53.

## Why a constant is not merely stale but actively wrong

Take a week in August 2026: the employee works 40 hours against a 42-hour week and the report shows 2 hours under. In 2028 the limit becomes 40 and the same week is reprinted. Reading a single scalar, the report now says that week was exactly on target — the deficit disappears, and a closed period gives a different answer than it gave the first time. A report that changes its mind about the past is not defensible in front of an inspector, and it is not reconcilable against a payroll run that already happened.

So the limits are **date-versioned**, and four properties have to hold together for that to actually work. Each is testable and each is easy to lose accidentally:

**1. Versions are append-only.** A new law adds a row with its own effective-from date. The 42-hour row is never edited to say 40 — editing in place destroys the past exactly as a scalar does, only less visibly. A genuine data-entry error in a version needs an explicit correction path that recalculates every affected day, not a quiet update.

**2. The resolver takes a date and has no default.** There is no `current()` and no implicit `now()`. This is the structural guard for the whole bug class, which always looks like someone reaching for the newest row when they meant the applicable one. If the only way to obtain a limit is to say which date it is for, the property holds by construction instead of by everyone remembering.

**3. Resolve and stamp, both.** The figure is resolved from the date at calculation time, and the version used is stamped onto the calculated day. The stamp is not what drives the number — it is what proves which rule was applied, and what surfaces drift if the two ever disagree.

**4. A transition mid-week needs a written rule.** Ley 21.561 took effect on Sunday 26 April 2026, landing almost cleanly on a week boundary; the next change may not. If a limit changes on a Wednesday, the week it falls in is judged against one value or the other, and which one is a decision to record with its reasoning — not something to leave to whatever the query happens to do. The daily caps have no such problem. KOL-41 defines the week itself and must agree with whatever is decided here.

## What needs to be resolvable for an arbitrary date

- ordinary weekly hours (45 → 42 from 2026-04-26 → 40 from 2028, per Ley 21.561)
- maximum overtime per day (2h, Código del Trabajo art. 31)
- maximum overtime per week (12h)
- maximum ordinary + extraordinary per day (12h) and per week

## Ownership: global, not per tenant

**These are global.** This is a deliberate departure from PRD section 9, which lists legal-cap parameters as per-tenant configurable. The reasoning: these values *are* the law of Chile, identical for every employer in the country. A tenant able to edit them is a tenant able to quietly raise their own overtime ceiling and have Kolvi endorse the resulting figure in an audit — the exact failure this module exists to prevent. What genuinely varies per tenant is policy, not law, and that lives in KOL-37: authorisation mode, pacto requirement, volume thresholds, compensation type.

Tenant application code reads the resolved limits and has no write path. Maintenance happens in the SaaS panel (KOL-53).

Deliberately **out of scope**: a per-tenant exception for an employer holding a DT-authorised jornada excepcional (art. 38). That case is real but rare, and building an override now would reintroduce the tenant-editable surface this task exists to remove. If it comes up, the shape to reach for is a Kolvi-staff-set exception in the SaaS panel — never tenant self-service.

Audit the existing consumers of `config('ams.max_weekly_hours')` and `config('ams.max_daily_hours')` before changing anything — shift validation already reads them, and this task must not change what that screen reports for a shift saved today.

Reference: docs/PRD_Overtime_Module_Kolvi_EN.md sections 5 and 9.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Ordinary weekly hours, daily and weekly overtime caps, and the combined ordinary-plus-extraordinary ceilings are resolvable for any given date rather than read from a constant
- [x] #2 The seeded baseline reflects the real timeline of Ley 21.561: 45h until 2026-04-25, 42h from 2026-04-26, 40h from its statutory date
- [x] #3 The resolver requires an explicit date and offers no current-value accessor and no implicit default to today, so a historical calculation cannot silently pick up the newest version
- [x] #4 Limit versions are append-only: a new law adds a version and never edits an existing one, and there is no path by which editing a version silently changes an already-calculated day
- [x] #5 A correction to a mistaken version is possible only through an explicit flow that recalculates every day it affected, rather than as a quiet update
- [x] #6 Each calculated day is stamped with the limit version it was judged against, and a mismatch between the stamp and what the date would resolve to today is detectable
- [x] #7 Adding a new future version leaves every previously calculated and previously reported figure byte-for-byte unchanged; the regression test reprints a past period before and after adding a version and asserts identical output
- [x] #8 The rule for a week straddling a limit change is decided, implemented and documented in the notes with its reasoning, and agrees with the week definition KOL-41 establishes
- [x] #9 Tenant application code can read the resolved limits it needs and has no write path to them
- [x] #10 Every existing consumer of the two `config/ams.php` hour constants is migrated, and shift validation reports the same result it did before for a shift saved today
- [x] #11 Pest tests cover a date before and after a limit change, a date with no version defined, a recalculation of an old day after a newer version exists, and a week straddling a change
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
1. Migration create_legal_hour_limits_table (global, no organization_id) + baseline rows inserted in the same migration rather than a seeder, so every environment and every test DB resolves a limit without extra setup.
2. Baseline = the real Ley 21.561 timeline, four versions: 45h from 2005-01-01 (Ley 19.759), 44h from 2024-04-26, 42h from 2026-04-26, 40h from 2028-04-26. AC #2's parenthetical omits the 44h step; the criterion says 'the real timeline' and the 44h step is real, so it is seeded.
3. Migration add_legal_hour_limit_id_to_workdays_table: nullable FK, restrictOnDelete so a stamped version cannot be deleted.
4. Model LegalHourLimit with an append-only guard in booted(): update and delete throw unless inside the explicit correction flow; create only through the version-adding path.
5. Resolver App\Services\LegalHourLimits: on(CarbonInterface), forWeekOf(CarbonInterface), static weekStart(). No current()/today()/latest(), no defaulted date argument; throws MissingLegalHourLimit when no version covers the date. Scoped binding so per-date resolution is one query.
6. Week rule: ISO week Monday-Sunday (matches DailyReportService, the DT-certified report), judged against the limit in force on the week's Monday. Documented in notes + architecture.md for KOL-41.
7. WorkdayCalculator stamps legal_hour_limit_id on insert and on recalculate; LegalHourLimitDrift compares every stamp against what its date resolves to now.
8. Actions\CorrectLegalHourLimit: requires a reason, unlocks the guard, updates, recalculates every workday stamped with the version, logs to the activity log.
9. Migrate consumers: ShiftController (index/create/edit/validate) and ShiftDay::exceedsLegalMaxHours read the resolver for today; remove max_weekly_hours and max_daily_hours from config/ams.php.
10. Tests: LegalHourLimitTest (before/after a change, undefined date, append-only, correction + recalculation, drift, straddling week, no tenant write path, DailyReportService reprint identical before/after adding a future version) plus updated ShiftManagementTest.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Week straddling a limit change — decision (AC #8)

A week is **Monday–Sunday**, and a week straddling a limit change is judged against the version in force on its **Monday**.

Monday–Sunday because DailyReportService — the report the DT certified — already totals by ISO week, and a second week definition in the same product would eventually disagree with it.

Monday-governs because two of the three Ley 21.561 steps land mid-week (26 Apr 2024 was a Friday, 26 Apr 2028 is a Wednesday). The weekly cap is a budget spent across the week: applying a newly lowered ceiling from Wednesday would retroactively turn hours already lawfully worked on Monday and Tuesday into an excess against a ceiling that did not exist when they were worked. Taking the week's opening rule means both parties know the ceiling before the week starts and no already-worked hour ever changes character. The daily caps have no such problem and resolve per day.

Exposed as LegalHourLimits::forWeekOf(); recorded on KOL-41 and in docs/architecture.md so KOL-41 and KOL-24 inherit it.

## Baseline seeded: the 44h step AC #2 omitted

AC #2's parenthetical says '45h until 2026-04-25, 42h from 2026-04-26', which skips a real step. Ley 21.561 was published 26 Apr 2023 and reduces the week at one, three and five years from publication: 44h from 2024-04-26, 42h from 2026-04-26, 40h from 2028-04-26. The criterion asks for 'the real timeline', so all four versions are seeded (45h from 2005-01-01 under Ley 19.759 as the opening row). Seeded in the migration, not a seeder: nothing can resolve a limit without these rows, so every environment and every test database needs them, not just the ones that ran db:seed.

## Deviation from AC #10, per user decision

AC #10 asked that shift validation report the same result as before for a shift saved today. That cannot hold together with AC #2: the old constant was 45 and the law today is 42, so a 43–45h shift that used to save now fails. Asked and decided in favour of the real resolved limit — leaving the shift screen endorsing 45-hour schedules is the exact failure this module exists to prevent. Consumers are otherwise migrated mechanically; the existing ShiftManagementTest was updated and a new test pins the 44h-rejected / 42h-accepted boundary with time frozen inside the 42h window. Existing tenants holding a 45h shift cannot re-save it until KOL-41 makes the cap advisory per Res. 38 art. 45.2 — worth sequencing KOL-41 soon after.

## Pre-existing test failure, unrelated

tests/Feature/Api/MarkApiTest 'yesterday punch does not block today' fails on clean master as well (verified by stashing this branch). It is a time-of-day flake: the test seeds the previous mark with now()->subDay() in UTC while the punch endpoint dates the new mark in Santiago, so between 00:00 and 04:00 UTC both land on the same Chilean calendar date and the one-per-day guard returns 409. Not touched here — needs its own task.

**Update:** the MarkApiTest flake above was fixed on this branch at the user's request rather than deferred. One line: the test now seeds the prior mark with Carbon::now($employee->timezone)->subDay() instead of a UTC now()->subDay(), so 'yesterday' is yesterday on the employee's calendar — the same clock the one-per-day guard reads. Verified green during the 00:00–04:00 UTC window that was breaking it. Full suite: 780 tests, 776 passed, 4 skipped, 0 failures.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Chile's legal working-hour limits are now date-versioned rows in a global `legal_hour_limits` table instead of two scalars in config/ams.php. App\Services\LegalHourLimits resolves them and requires the date it is asked about — no current(), no latest(), no default to today — so a historical calculation cannot pick up a newer rule; an uncovered date throws MissingLegalHourLimit rather than borrowing the nearest one. Versions are append-only: the model refuses update/delete, a restrict FK from workdays enforces the delete half in the database, and a mistaken version is fixable only through App\Actions\CorrectLegalHourLimit, which demands a written reason and recalculates every affected day before returning. WorkdayCalculator stamps workdays.legal_hour_limit_id from each day's own date (existing rows backfilled in the migration) and LegalHourLimitDrift surfaces any stamp that no longer matches. The seeded baseline is the real Ley 21.561 timeline in four steps — 45h from 2005-01-01, 44h from 2024-04-26, 42h from 2026-04-26, 40h from 2028-04-26 — seeded in the migration rather than a seeder because nothing resolves without it. A week is Monday-Sunday judged against the version in force on its Monday, recorded on KOL-41 and in docs/architecture.md.

Deviation the user approved: AC #10's 'same result for a shift saved today' was dropped in favour of the real resolved limit. The old constant was 45 and the law today is 42, so a 43-45h shift that used to save is now rejected — leaving the shift screen endorsing 45-hour schedules is the failure this module exists to prevent. Consumers are otherwise migrated mechanically. Existing tenants holding a 45h shift cannot re-save it until KOL-41 makes the cap advisory per Res. 38 art. 45.2.

Verified: 32 new Pest tests in LegalHourLimitTest covering dates either side of each change, an undefined date, append-only refusals, correction with recalculation and restamping, drift detection, the straddling week, no tenant write path, and a DailyReportService reprint asserted byte-for-byte identical before and after appending a future version; plus updated ShiftManagementTest pinning the 44h-rejected/42h-accepted boundary. Full suite 780 tests, 776 passed, 4 skipped, 0 failures. Pint clean, PHPStan level 7 clean, tsc --noEmit clean.
<!-- SECTION:FINAL_SUMMARY:END -->
