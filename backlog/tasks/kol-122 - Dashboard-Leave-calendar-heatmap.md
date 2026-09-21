---
id: KOL-122
title: 'Dashboard: Leave calendar heatmap'
status: To Do
assignee: []
created_date: '2026-09-21 08:55'
labels: []
dependencies: []
ordinal: 119000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Add a calendar heatmap widget to the dashboard showing, for each day of the current month, how many employees are on approved leave — a monthly view built on the same Leave data as the existing 'Who's out today' widget (DashboardController::whosOut), rather than day-by-day. Scope visibility the same way: admins see the whole organization, supervisors holding ViewTeam:Leave see only their direct reports, everyone else does not see it.

## User stories for manual testing (Gherkin)

Scenario: Heatmap for a supervisor's team
  Given a supervisor holds ViewTeam:Leave and has direct reports with approved leave overlapping days in the current month
  When they visit the dashboard
  Then they see a heatmap of the current month with each day shaded by how many of their reports are out that day

Scenario: Heatmap for an admin
  Given an admin visits the dashboard
  Then the heatmap counts approved leave across the whole organization

Scenario: No permission
  Given a user holds neither the admin role nor ViewTeam:Leave
  When they visit the dashboard
  Then the leave heatmap is not shown

Scenario: No leave this month
  Given nobody in the visible team or organization has approved leave overlapping the current month
  When the user visits the dashboard
  Then the heatmap renders with every day at zero rather than erroring or disappearing
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Heatmap shows every day of the current month shaded by count of employees on approved leave that day, for the visible team/org
- [ ] #2 Visible only to users holding the admin role or ViewTeam:Leave; admins see org-wide, supervisors see only their direct reports (mirrors DashboardController::whosOut)
- [ ] #3 Renders a zero-count month cleanly rather than erroring or hiding the widget
- [ ] #4 Selecting a day shows which employees are out that day, reusing the existing 'who's out' list shape
- [ ] #5 A Pest test covers per-day counts, month boundaries, permission scoping, and the zero-leave case
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
