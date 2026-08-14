---
id: KOL-64
title: Upcoming shifts for the mobile app on GET /api/v1/me/shifts/upcoming
status: Done
assignee:
  - '@claude'
created_date: '2026-08-14 01:20'
updated_date: '2026-08-14 01:51'
labels:
  - api
  - mobile
dependencies: []
ordinal: 45000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-32 builds the Jornada tab's Próximos screen: today's shift plus the next N scheduled days, with days covered by an approved leave or a holiday annotated instead of shown as an ordinary shift. No endpoint expands a shift across a date range today — MarkManager::getShiftForDate resolves one date at a time — so this extracts a small ShiftScheduleResolver rather than duplicating that logic inline.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/v1/me/shifts/upcoming requires ViewOwn:Workday and 403s without it
- [x] #2 The days query param controls the horizon, defaults to 14, and is capped at 30
- [x] #3 The response's today block carries the employee's shift for the current date (premise, start_time, end_time, lunch_start_time, lunch_end_time) using the same field names /me/today already uses, or null when nothing is scheduled
- [x] #4 today.punch_state is present only when the employee holds ClockOwn:Mark, mirroring TodayController's own gating, and absent (not null) otherwise
- [x] #5 The days array covers each requested date in order, skipping any date whose ShiftDay is_free
- [x] #6 A date covered by an approved Leave (status=approved, start_date..end_date) reports leave_type_label from LeaveType::label() and omits the shift time fields for that date
- [x] #7 A date matching a Holiday reports holiday_name and omits the shift time fields for that date, unless the assigned Shift has work_on_holidays true
- [x] #8 A date with none of the above reports start_time, end_time, lunch_start_time, lunch_end_time and premise
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
vendor/bin/pint --dirty --format agent: clean. vendor/bin/phpstan analyse (memory-limit=1G) on every touched file: 0 errors. tests/Feature/Api/UpcomingShiftsApiTest.php: 14/14 passing, run via host PHP 8.5 against the already-running mysql container in an isolated testing_kol64 database (this worktree's own .env.testing, DB_HOST 127.0.0.1) — a second isolated database rather than the shared 'testing' one KOL-62's notes used, because another process was mid-run against 'testing' on this same container when this ticket started and collided with it. tests/Feature/Api/TodayApiTest.php re-run in full: 22/22 still passing, confirming the MarkManager::punchStateForDate extraction (moved out of TodayController's own private method, now shared with UpcomingShiftsController, identical logic) did not regress the existing endpoint. tests/Feature/Api/MarkApiTest.php and OfflineMarkApiTest.php also re-run for the same reason (MarkManager is used broadly): all passing; the only failures in that broader run (DtValidateMarkTest, My/MarkTest) are pre-existing Vite-manifest-missing errors on Inertia page routes, unrelated to this change and unrelated to the API surface it touches — this worktree has no frontend build, same class of environmental gap KOL-62 flagged for 'sa test'. 'sa test --compact' (DoD #2) not run as literally specified for the same reason KOL-62 documented: no bound Sail stack in this worktree. DoD #3 not applicable, no TypeScript touched.

Two real bugs found and fixed during testing, both timezone-instant vs calendar-date comparison mistakes: (1) ShiftScheduleResolver's per-day loop variable carried the employee's IANA timezone (America/Santiago) while Leave/Holiday date columns cast to midnight in the app's default timezone, so Carbon's lte/gte silently disagreed about which day came first on a same-calendar-date leave; fixed by comparing toDateString() throughout resolveDate() rather than Carbon instants. (2) The test asserting the days=30 cap made the same mistake against the API's JSON string dates; fixed the same way. Left a comment on ShiftScheduleResolver explaining why string comparison is deliberate there, since the instinct otherwise is to compare Carbon instances directly.

Follow-up fix, found while wiring the mobile client (KMO-32): the wire shape only carried today's own date inside the nullable today block, so a free day (today=null) gave the client no way to know today's actual calendar date — needed to decide which upcoming row is literally 'Mañana'. Added a top-level date field (always present, same reading TodaySummary's own $date takes), amended into the same commit since the branch was still local and unpushed. 15/15 tests passing (added one covering date on a day with no shift), Pint and Larastan clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
GET /api/v1/me/shifts/upcoming (permission:ViewOwn:Workday) answers with today's shift/punch state plus the schedule for the days after it, via a new ShiftScheduleResolver that expands a shift assignment across a date range in two bounded queries (modelled on BusinessDaysCalculator's own shape) rather than looping MarkManager's single-date lookup. Free (is_free) days are omitted; a date an approved Leave or a Holiday the shift doesn't work covers keeps its row but reports leave_type_label/holiday_name instead of a schedule, leave taking priority when both apply. MarkManager gained punchStateForDate(), extracted from TodayController's own private method so both controllers share one reading of 'where is this employee in their day' instead of two copies of the same Mark query. Verified with 14 new Pest tests plus a full re-run of TodayApiTest (22/22) and the broader Mark API suite to confirm the MarkManager extraction didn't regress anything; Pint and Larastan both clean. Left on its own branch (feature/kol-upcoming-shifts, worktree at ams-worktrees/kol-upcoming-shifts) — not merged to ams master, pending approval, same as KOL-61/62 before it.
<!-- SECTION:FINAL_SUMMARY:END -->
