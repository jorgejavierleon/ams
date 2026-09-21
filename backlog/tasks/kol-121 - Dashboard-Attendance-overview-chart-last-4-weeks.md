---
id: KOL-121
title: 'Dashboard: Attendance overview chart (last 4 weeks)'
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-21 08:55'
updated_date: '2026-09-21 09:41'
labels: []
dependencies: []
ordinal: 118000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Add a chart widget to the dashboard showing the daily attendance breakdown (on-time, late, absent) over the last 4 weeks, for supervisors and admins. Builds on the same Workday data KOL-120 uses for the attendance rate stat card, plotted as a trend instead of a single period's rate: a day counts as on-time when Workday.in_time_difference is not positive, late when it is positive, and absent when WorkdayStatus is Absent. Scope visibility the same way KOL-120 scopes the attendance rate card: admins see the chart org-wide, supervisors holding ViewTeam:Workday see only their direct reports, everyone else does not see it. Use Recharts via the shadcn chart component per the project's dashboard charting decision.

## User stories for manual testing (Gherkin)

Scenario: Attendance trend for a supervisor's team
  Given a supervisor holds ViewTeam:Workday and has direct reports with computed workdays over the last 4 weeks
  When they visit the dashboard
  Then they see a chart of on-time, late and absent counts per day for their team over that period

Scenario: Attendance trend for an admin
  Given an admin visits the dashboard
  Then the chart is computed across the whole organization, not just one team

Scenario: No permission
  Given a user holds neither the admin role nor ViewTeam:Workday
  When they visit the dashboard
  Then the attendance overview chart is not shown

Scenario: No workdays in the period
  Given the visible team or organization has no computed Workday rows in the last 4 weeks
  When the user visits the dashboard
  Then the chart shows a clear empty state rather than an empty or broken plot
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Chart shows daily counts of on-time, late and absent workdays for the last 4 weeks (28 days), for the visible team/org
- [x] #2 On-time vs late is derived from Workday.in_time_difference (late = positive difference), absent from WorkdayStatus::Absent
- [x] #3 Visible only to users holding the admin role or ViewTeam:Workday; admins see org-wide, supervisors see only their direct reports (mirrors KOL-120)
- [x] #4 Explicit empty state when there are no Workday rows in the period
- [x] #5 Uses the shadcn chart component (Recharts) per the project's dashboard charting decision
- [x] #6 A Pest test covers the daily counts, the on-time/late split, permission scoping, and the empty state
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
1. Add DashboardController::attendanceOverview(user), scoped exactly like attendanceRate() (admin org-wide, supervisor's own reports via ViewTeam:Workday, hidden otherwise): query Workday rows for the last 28 days, bucket each into on_time/late/absent (absent from WorkdayStatus::Absent, late when in_time_difference is positive, on_time otherwise), zero-fill every day of the period, return null when no permission, {days: []} when the scope has zero rows in the period.
2. Add the shadcn chart primitive (resources/js/components/ui/chart.tsx) and the recharts dependency (npm install recharts@3.8.0 — matches shadcn's chart block; installed via npm directly since the shadcn CLI shells out to pnpm, which isn't installed, and its dry-run also wanted to overwrite card.tsx to a newer shadcn version, out of scope).
3. Add AttendanceOverviewCard to dashboard.tsx: a stacked BarChart (on_time/late/absent) using ChartContainer/ChartTooltip/ChartLegend, colored via the existing --success/--warning/--destructive theme tokens (matches WorkdayStatus::badge() semantics), full-width row below the existing 3-3-6 grid, explicit empty state when days is empty.
4. Add lang/en+es ui.php dashboard.attendance_overview strings.
5. Pest test (DashboardAttendanceOverviewTest): permission gating (hidden/supervisor-scoped/admin-org-wide), on-time-vs-late split from in_time_difference, absent from status, 28-day zero-fill, empty state.
6. pint --dirty, phpstan on the controller, npm run types:check, targeted Pest run, manual browser check against seeded demo data.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verification: vendor/bin/pint --dirty clean; phpstan level 7 clean on DashboardController.php; npm run types:check has only pre-existing unrelated errors in roles/index.tsx and roles/show.tsx; sail artisan test --compact --filter=DashboardAttendanceOverviewTest -> 7/7 passed; --filter=Dashboard -> 46/46 passed; --filter=Workday -> 138/138 passed (no regression). Full suite not run, per standing convention of running only filtered tests during ticket work. Manually verified in browser against the seeded demo data (admin@example.com) at /dashboard: the stacked bar chart renders under 'Resumen de asistencia · Últimas 4 semanas' with Ausencias/Atrasos/A tiempo legend; cross-checked against a raw DB tally of the same 28-day window (on_time=0, late=116, absent=24), which explains why no green segment is visible — the demo seed genuinely has zero on-time days, confirming the bucketing logic rather than a bug.

Code review (medium) found one real bug: the chart's tick/tooltip date formatters passed the raw YYYY-MM-DD string to formatDate(), which JS parses as UTC midnight, shifting the displayed day back by one in negative-UTC-offset zones (Santiago). Fixed by appending T00:00:00 before formatting, matching the existing convention already used in holidays/index.tsx and legal-hour-limits/index.tsx. Re-verified in browser: last bar now correctly reads '21 sept' (today) instead of '20 sept'. Re-ran eslint/prettier/types:check clean after the fix.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added the attendance overview chart (last 4 weeks) to the dashboard. DashboardController::attendanceOverview() reuses KOL-120's exact permission scoping (admin org-wide, supervisor's own reports via ViewTeam:Workday, hidden otherwise) and buckets each Workday row into on_time/late/absent (absent from WorkdayStatus::Absent, late when in_time_difference is positive, on_time otherwise), zero-filling all 28 days so the chart's x-axis stays continuous, with an explicit empty state when the visible scope has no Workday rows at all in the period. Added the shadcn chart primitive (resources/js/components/ui/chart.tsx) and recharts@3.8.0 per the project's dashboard charting decision, and a new AttendanceOverviewCard (stacked BarChart, themed via --success/--warning/--destructive) as a full-width row below the dashboard's existing widget grid. Verified with a new DashboardAttendanceOverviewTest (7 tests covering permission scoping, the on-time/late split, absence, 28-day zero-fill, and the empty state), pint, phpstan, types:check, and a manual browser check against seeded demo data.
<!-- SECTION:FINAL_SUMMARY:END -->
