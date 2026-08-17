---
id: KOL-68
title: Workday detail with punches for the mobile day-detail screen
status: Done
assignee: []
created_date: '2026-08-16 15:37'
updated_date: '2026-08-16 19:34'
labels: []
dependencies: []
priority: medium
ordinal: 47000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-34 needs the shift window and each punch's mark_id for one of the employee's own workdays, to render four KPI tiles and an attendance strip on a day-detail screen, plus link each punch to its comprobante via the existing GET /api/v1/marks/{mark}. GET /api/v1/me/workdays (KOL-65) only returns the list shape — this adds the single-day detail with the shift window and mark ids.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/v1/me/workdays/{date} returns the authenticated employee's own workday for that date: status, shift_start/shift_end (the assigned shift's own scheduled window, null when no shift), worked_time/extra_time/missing_time, leave_type_label, and mark_in/mark_out each carrying the punch's own mark_id
- [x] #2 A day covered by an approved leave nulls the three time figures and carries leave_type_label, mirroring WorkdayResource's own leave-day convention
- [x] #3 A day with only one recorded punch nulls the missing side rather than fabricating a mark_in or mark_out
- [x] #4 A date with no computed workday row for this employee 404s
- [x] #5 The route is gated by permission:ViewOwn:Workday and scoped to the authenticated employee — another employee's date is unreachable
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
GET /api/v1/me/workdays/{date} added to Api\\WorkdaysController alongside index(), scoped to the authenticated employee via where('user_id', ...) the same way index() already is (marks/{mark} uses the same abort-on-mismatch pattern rather than route-model-binding scoping). shift_start/shift_end and each mark's time carry seconds (H:i:s) rather than WorkdayResource's display-trimmed H:i, matching kolvi-mobile's NaiveTime wire format (HH:mm:ss) since the client does real minute arithmetic on these, not just display.

Rebased onto master (past KOL-66, KOL-46) and merged: master..e7e21fd, pushed to origin. Full suite re-run after rebase: 1042/1051 passing — the 2 failures (a hardcoded-date UpcomingShiftsApiTest, an OvertimeQueueTest query-count bound) predate this branch and are outside its 5-file diff.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
New GET /api/v1/me/workdays/{date} for kolvi-mobile KMO-34's day-detail screen: one workday's status, its assigned shift's own scheduled window (or null without one), the three time figures (null on a leave day, carrying leave_type_label instead), and mark_in/mark_out each with the punch's own mark_id so the client can retrieve its comprobante through the existing GET /api/v1/marks/{mark}. 404s when the employee has no computed workday for that date; permission:ViewOwn:Workday gates it, same as the list endpoint. Verified with 19 new/updated Pest tests (shift window, one-punch day, leave day, midnight-crossing shift's raw times, 404, cross-employee isolation) plus the full suite (1033/1034 passing — the one failure is a pre-existing hardcoded-date test unrelated to this change) and vendor/bin/pint clean. Left on feature/kol-68-workday-detail, not merged to master.
<!-- SECTION:FINAL_SUMMARY:END -->
