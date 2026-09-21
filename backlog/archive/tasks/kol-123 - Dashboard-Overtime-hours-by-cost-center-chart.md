---
id: KOL-123
title: 'Dashboard: Overtime hours by cost center chart'
status: To Do
assignee: []
created_date: '2026-09-21 08:55'
labels: []
dependencies: []
ordinal: 120000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Add a bar chart widget to the dashboard showing total approved overtime hours (OvertimeAuthorization.final_hours where status is Approved) aggregated by cost center for the current payroll period, for supervisors and admins. Highlights any cost center whose total is notably high relative to the applicable LegalHourLimit's weekly overtime cap, so admins can spot a compliance risk before payroll export. Scope visibility via ViewTeam:OvertimeAuthorization (supervisors see only cost centers containing their own direct reports) and the admin role (org-wide), mirroring the pattern used by KOL-120 and the other dashboard widgets.

## User stories for manual testing (Gherkin)

Scenario: Overtime by cost center for an admin
  Given several cost centers have approved overtime authorizations this period
  When an admin visits the dashboard
  Then they see total approved overtime hours per cost center across the whole organization

Scenario: Overtime by cost center for a supervisor
  Given a supervisor holds ViewTeam:OvertimeAuthorization
  When they visit the dashboard
  Then they see overtime totals only for cost centers containing their direct reports

Scenario: Cost center near the legal limit
  Given a cost center's aggregated overtime this period is close to or over the applicable LegalHourLimit's weekly overtime cap
  When the chart renders
  Then that cost center's bar is visually flagged as at risk

Scenario: No permission
  Given a user holds neither the admin role nor ViewTeam:OvertimeAuthorization
  When they visit the dashboard
  Then the overtime-by-cost-center chart is not shown
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Chart shows total approved overtime hours (final_hours on Approved OvertimeAuthorization rows) grouped by cost center for the current payroll period
- [ ] #2 Visible only to users holding the admin role or ViewTeam:OvertimeAuthorization; admins see all cost centers, supervisors see only cost centers containing their direct reports
- [ ] #3 A cost center whose aggregated hours are at or near the applicable LegalHourLimit's weekly overtime cap is visually flagged
- [ ] #4 Explicit empty state when there is no approved overtime in the period
- [ ] #5 A Pest test covers the aggregation, the at-risk flag, permission scoping, and the empty state
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
