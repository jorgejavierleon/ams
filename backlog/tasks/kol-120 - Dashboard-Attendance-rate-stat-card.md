---
id: KOL-120
title: 'Dashboard: Attendance rate stat card'
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-20 18:26'
updated_date: '2026-09-20 22:47'
labels: []
dependencies: []
ordinal: 117000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Add a stat card to the dashboard showing the team's attendance rate for the current week vs last week, for supervisors and admins. Attendance is already computed per employee per day on Workday.status (WorkdayStatus: Regular/Irregular/Justified/Incomplete count as attended, Absent does not) by WorkdayCalculator, so this card aggregates existing data rather than computing new attendance logic. Scope the visibility the same way DashboardController::whosOut() scopes the existing 'Who's out' widget: admins see the rate across the whole organization, supervisors holding ViewTeam:Workday see only their direct reports, everyone else does not see the card.

## User stories for manual testing (Gherkin)

Scenario: Attendance rate for a supervisor's team
  Given a supervisor holds ViewTeam:Workday and has direct reports with computed workdays this week
  When they visit the dashboard
  Then they see the attendance rate for their team this week, compared to last week's rate

Scenario: Attendance rate for an admin
  Given an admin visits the dashboard
  Then they see the attendance rate computed across the whole organization, not just one team

Scenario: No permission
  Given a user holds neither the admin role nor ViewTeam:Workday
  When they visit the dashboard
  Then the attendance rate card is not shown

Scenario: No scheduled workdays in the period
  Given the visible team or organization has no computed Workday rows for the current week
  When the user visits the dashboard
  Then the card shows a clear empty or neutral state rather than a misleading 0% or a division-by-zero
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Card shows attendance rate = attended workdays / total scheduled workdays for the current week, where attended is any WorkdayStatus other than Absent
- [x] #2 Card shows the trend/delta versus the prior week (percentage point change)
- [x] #3 Visible only to users holding the admin role or ViewTeam:Workday; admins see the rate org-wide, supervisors see only their direct reports
- [x] #4 Card shows an explicit empty/neutral state when there are no scheduled Workday rows in the period, instead of 0% or a division-by-zero
- [x] #5 A Pest test covers the rate calculation, the week-over-week trend, the permission scoping (admin, supervisor, unauthorized), and the empty state
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Backend: add DashboardController::attendanceRate(User $user): ?array, scoped exactly like whosOut() (admin org-wide via hasRole('admin'), supervisor's direct reports via ViewTeam:Workday, null otherwise). Computes current ISO week (Mon-Sun, Carbon::MONDAY/SUNDAY) and prior week's Workday counts (total rows vs status=Absent rows) scoped by supervisor_id when applicable; returns ['rate' => float|null, 'trend' => float|null], both null when the period has zero Workday rows. Wire into index() as 'attendanceRate' prop.
2. Frontend: add AttendanceRate type + prop to resources/js/pages/dashboard.tsx; add AttendanceRateCard component (percentage headline, trend delta with up/down/flat icon, explicit empty state) rendered in the existing col-span-3 row alongside PendingApprovalsCard/WhosOutCard when attendanceRate is not null.
3. Add lang/en/ui.php + lang/es/ui.php dashboard.attendance_rate.* strings (title, trend phrasing, empty state).
4. Pest test tests/Feature/DashboardAttendanceRateTest.php mirroring DashboardWhosOutTest.php: admin sees org-wide rate, supervisor sees only own reports, no-permission user gets null, empty state when no scheduled workdays this week, rate and week-over-week trend math.
5. vendor/bin/pint --dirty, sail artisan test --compact --filter=DashboardAttendanceRate, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented DashboardController::attendanceRate() (Mon-Sun week, scoped like whosOut()), AttendanceRateCard on dashboard.tsx, en/es strings, DashboardAttendanceRateTest (5 tests, all passing). Pint clean. Verified visually via chrome-devtools-mcp as supervisor: empty state and populated 75% + -10.3pt trend both render correctly. Running /code-review before commit.

DoD #2 (full test suite) intentionally left unrun/unchecked per user preference: only filtered tests during ticket work, full suite requires explicit go-ahead. Ran targeted: sail artisan test --compact --filter=Dashboard -> 39 passed, 257 assertions. /code-review medium returned no findings.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added DashboardController::attendanceRate(), scoped exactly like whosOut() (admin org-wide, ViewTeam:Workday supervisor sees own reports, else hidden), computing attended/scheduled Workday rows for the current Mon-Sun week vs the prior week (null rate/trend when the period has zero Workday rows). Added AttendanceRateCard to resources/js/pages/dashboard.tsx (percentage, trend arrow with pt delta, explicit empty state) plus en/es lang strings. Verified with tests/Feature/DashboardAttendanceRateTest.php (5 tests: hidden for no-permission user, supervisor team-scoped rate, admin org-wide rate, week-over-week trend, empty state) and visually via chrome-devtools-mcp logged in as the seeded supervisor (both empty state and a 75%/-10.3pt populated state rendered correctly). Pint clean, npm run types:check shows no new errors (2 pre-existing unrelated errors in roles pages confirmed via git stash), /code-review medium returned no findings.
<!-- SECTION:FINAL_SUMMARY:END -->
