---
id: KOL-84
title: Fix ShiftScheduleResolver dropping the tail of a multi-day range
status: In Review
assignee: []
created_date: '2026-08-24 10:46'
updated_date: '2026-08-24 10:55'
labels:
  - bug
  - mobile-api
dependencies: []
references:
  - tests/Feature/Api/UpcomingShiftsApiTest.php
  - app/Services/ShiftScheduleResolver.php
  - app/Http/Controllers/Api/UpcomingShiftsController.php
ordinal: 62000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
GET /api/v1/me/shifts/upcoming (the Jornada tab's Próximos screen, KOL-64) returns fewer upcoming days than its own `days` horizon promises. `UpcomingShiftsController` asks `ShiftScheduleResolver::resolve()` for the range `[today+1, today+$horizon]`; with the default horizon (14) and a Mon-Fri shift assignment starting well before today, resolving that range in isolation returns only 9 entries ending 3 calendar days short of the range end, even though resolving the single end date alone (`resolve($user, $end, $end)`) correctly returns an entry for it. The internal day-by-day loop appears to stop iterating before it reaches the requested end date, rather than the missing days being legitimately-skipped weekends or holidays. Confirmed while working KOL-83 (unrelated ticket): `sa test --compact --filter=UpcomingShiftsApiTest` fails deterministically (not flaky) on `the days param controls the horizon and defaults to 14`, expecting `2026-09-07` and getting `2026-09-04`. Reproduced independently via tinker against a fresh org/employee/shift/assignment fixture matching the test's own `upcomingShift()` helper.

## User stories for manual testing (Gherkin)

```gherkin
Feature: Próximos schedule horizon
  Scenario: An employee views the full two-week schedule
    Given an employee with a Monday-Friday shift assignment starting well before today
    And no leaves or holidays in the next two weeks
    When they open the Jornada tab's Próximos screen on the mobile app
    Then the schedule shows every working day from tomorrow through 14 days out
    And the last working day shown is the one closest to (on or before) that 14th day, not several days earlier
```
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 ShiftScheduleResolver::resolve() returns an entry for every non-free, non-holiday-blocked working day in the requested [start, end] range inclusive of end, matching what resolving that single end date alone already returns for it
- [x] #2 GET /api/v1/me/shifts/upcoming with no days param returns days through the working day on or before today+14 (not earlier), and the existing UpcomingShiftsApiTest::"the days param controls the horizon and defaults to 14" test passes
- [x] #3 A regression test pins the exact bug reproduced here: a resolve() call over a range whose weekend-then-workday tail (e.g. Fri, Sat, Sun, Mon) sits within the range must include that trailing Monday
- [x] #4 sa test --compact --filter=UpcomingShiftsApiTest passes in full, and the days=90 cap test (capped at 30) still passes
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
1. Root-caused via tinker: ShiftScheduleResolver::resolve()'s day-by-day loop only snapped $date to startOfDay() once, before the loop; addDay() on subsequent iterations preserves whatever wall-clock time $date currently carries. Chile's DST spring-forward (first Sunday of September) skips local 00:00-00:59, so the first addDay() landing on that date gets bumped to 01:00, and every day after inherits that hour of drift — eventually pushing $date past $end and truncating the range's last day(s). 2. Fix: re-snap to startOfDay() on every loop iteration, not just before it. 3. Add a regression test pinned to a fixed Carbon::setTestNow() date (not the real clock) so it keeps testing the DST crossing on any future run.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified: pint --dirty clean. sa test --compact --filter=UpcomingShiftsApiTest: 16/16 passed (including the new DST regression test and the previously-failing horizon test). Full sa test --compact: 1156/1160 passed, 4 skipped (pre-existing, unrelated) — no regressions from the fix.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Fixed ShiftScheduleResolver::resolve()'s day-by-day loop, which re-snapped $date to local midnight only once before the loop rather than on every iteration. Chile's DST spring-forward (first Sunday of September) skips local 00:00-00:59, so the first addDay() crossing that gap got bumped to 01:00 and every subsequent day inherited that hour of drift, eventually pushing $date past $end and silently truncating the range's last day(s) — reproduced as GET /api/v1/me/shifts/upcoming's default 14-day horizon coming up 3 days short. Fix re-snaps to startOfDay() every iteration. Verified with the pre-existing UpcomingShiftsApiTest suite (now fully passing) plus a new regression test pinned to a fixed Carbon::setTestNow() date so it exercises the DST crossing on any future run.
<!-- SECTION:FINAL_SUMMARY:END -->
