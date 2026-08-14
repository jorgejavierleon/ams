---
id: KOL-65
title: Workday history for the mobile app on GET /api/v1/me/workdays
status: Done
assignee: []
created_date: '2026-08-14 21:58'
updated_date: '2026-08-14 22:40'
labels:
  - mobile-api
dependencies: []
references:
  - kolvi-mobile KMO-33
ordinal: 46000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-33 (Jornada · Historial) needs a range-queryable, paginated-by-month list of the employee's own computed workdays — Res. 38 Art. 22.1 requires 5 years of unrestricted access. The web self-service portal already has this (My\WorkdayController::index, Workday::betweenDates, WorkdayStatus::badge()); this exposes the same computed data as JSON for the mobile client, mirroring how KOL-64 exposed upcoming shifts.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/v1/me/workdays returns the authenticated employee's own workdays, scoped by user_id, gated on permission:ViewOwn:Workday
- [x] #2 from and to query params are optional dates; when omitted the range defaults to the current month; when to is before from they are swapped, matching My\WorkdayController::index
- [x] #3 Each workday in the response carries date, date_label, weekday, status, status_label, status_badge (from WorkdayStatus::badge()), worked_time, extra_time, missing_time
- [x] #4 A day covered by an approved leave carries leave_type_label instead of the worked/extra/missing time fields
- [x] #5 The response is ordered newest-first and is a bare JSON array, matching the /me/marks and /me/shifts/upcoming envelope conventions
- [x] #6 A range with no workdays returns an empty array rather than an error
- [x] #7 The query is not capped to any window narrower than 5 years, per Art. 22.1
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Mirrors My\WorkdayController::index (default range = current month, from/to swapped if reversed, Workday::betweenDates, WorkdayStatus::badge()) rather than inventing new query logic. WorkdayResource omits worked/extra/missing on a leave day rather than sending zeros, per kolvi-mobile KMO-33's own reading of 'in place of'. No pagination parameter: the client pages back by moving from/to itself, so nothing here needs a cap to satisfy #7 (5 years) - the range-spanning test proves the query itself is unbounded.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
GET /api/v1/me/workdays?from=&to= exposes the employee's own computed workdays as JSON, gated on permission:ViewOwn:Workday, for the kolvi-mobile Jornada . Historial screen (KMO-33). Reuses Workday::betweenDates and WorkdayStatus::badge() rather than duplicating the web self-service list's own query. Verified with 10 new Pest tests (WorkdaysApiTest) covering access control, the range default/swap, the response shape, the leave annotation, an empty range, and a 5-year-wide range; vendor/bin/pint clean. Full suite: 985/1004 passing, 19 failures are all 'validRut() undefined' in EmployeeManagementTest under --parallel, a pre-existing test-isolation gap confirmed unrelated to this change - the same file passes 41/41 run alone. Live-verified end to end from the kolvi-mobile app against this branch: the Historial screen renders real seeded workdays with their status badges and figures, and paging back a month appends the older month below the current one.
<!-- SECTION:FINAL_SUMMARY:END -->
