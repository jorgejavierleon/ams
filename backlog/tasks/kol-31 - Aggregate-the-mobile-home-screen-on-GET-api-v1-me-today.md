---
id: KOL-31
title: Aggregate the mobile home screen on GET /api/v1/me/today
status: Done
assignee: []
created_date: '2026-08-04 22:08'
updated_date: '2026-08-05 00:36'
labels: []
dependencies: []
documentation:
  - docs/prd-mobile-app.md
priority: high
type: feature
ordinal: 30000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The mobile app (kolvi-mobile, task KMO-15 "Home screen shell") draws its whole Marcaje tab — the shift card, the punch status line and the week summary — from a single request. That tab is the app's reason to exist: PRD goal G1 is time-to-punch under ten seconds from app open at p90, and a screen that fanned out to the shift, the marks and the week separately would spend three round trips on a warehouse connection before the punch button was live.

The route does not exist. KMO-15 is built and merged-ready against the contract below, so the mobile side is currently the only written form of it; on a device the screen paints its header and then the Spanish retry state. Six of KMO-15's nine acceptance criteria cannot be verified until this ships.

### Contract the mobile client expects

```json
{
  "date": "2026-08-04",
  "shift": {
    "premise": "Sucursal Ñuñoa",
    "start_time": "08:00:00",
    "end_time": "17:00:00",
    "lunch_start_time": "13:00:00",
    "lunch_end_time": "14:00:00"
  },
  "punch": { "state": "before" },
  "week": { "worked_hours": 32.5, "contracted_hours": 44 }
}
```

The authoritative reading is `src/features/marcaje/today-api.ts` in kolvi-mobile — that parser is what a response is graded against, and it is the file that changes if this endpoint lands on different names.

Notes on the shape:

- **Every time value must be a naive wall-clock string with no offset** — `YYYY-MM-DD` and `HH:mm:ss`. Not `toIso8601String()`, which is what `MarkResource` emits today (PRD §3.2). The app rejects an offset outright and shows its failure state, deliberately: a shift window silently re-read in the device's timezone is a different legal fact under Res. 38 Art. 8 with nothing on screen to say it moved. A resource written by copying `MarkResource` breaks on day one.
- `shift` is `null` (or omitted) when nothing is scheduled — a day off, or an employee between assignments. The app has an explicit Spanish empty state for it.
- `lunch_start_time` and `lunch_end_time` travel **both or neither**. The app draws no colación row for half a window, because `13:00 – ` on a card reads as a rendering bug rather than as missing data.
- `punch.state` is exactly `before`, `working` or `done`. No `break` / `afterbreak`: decision D-F1-a in kolvi-mobile's docs/design-decisions.md dropped colación as a punch type, which supersedes the PRD's older five-state table. The app treats any other value as *unknown* and shows no status line rather than guessing — telling an employee who punched in at 08:00 that they have not marked entrada is the one wrong answer on that screen that costs them a workday.
- `week.contracted_hours` is the shift's contracted weekly total (decision D-F1-d), **not** the statutory maximum under Ley 21.561 — the two differ during the 44 → 40 transition. PRD §11 open question #9 still lists this as undecided and says to ask a domain expert; the decision record has since settled it, and this ticket is the place to confirm it against whatever column actually holds it (`Shift.total_week_hours`?).
- A user **without** `ClockOwn:Mark` must still get a 200. They see the date, the shift and the week; the app hides only the punch surface. A 403 here breaks the whole tab for an admin who does not punch.
- Unknown keys are ignored by the client, so the geofence block the PRD asks for (premise lat/lng + radius, for KMO-16) can be added here now without breaking anything already built. Doing it in one pass is cheaper than versioning the resource later.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/v1/me/today returns a resource with date, shift, punch and week, behind auth:sanctum, and an unauthenticated request still gets 401
- [x] #2 Every date and time in the payload is a naive wall-clock string — YYYY-MM-DD and HH:mm:ss — with no timezone offset stamped on it
- [x] #3 shift is null when nothing is scheduled for the employee today, including a free day and an employee with no active assignment
- [x] #4 lunch_start_time and lunch_end_time are either both present or both null, never one without the other
- [x] #5 punch.state is one of before, working or done, derived from today's marks — never a break or afterbreak state
- [x] #6 week.contracted_hours is the shift's contracted weekly total and week.worked_hours is the sum of the ISO week's worked time to date, both non-negative
- [x] #7 An authenticated user without ClockOwn:Mark receives 200 with the shift and week populated, not a 403
- [x] #8 The whole response is produced without an N+1 across shift, premise and the week's workdays
- [x] #9 A Pest feature test covers: a seeded employee with a shift and colación, an employee with no shift today, a shift with no colación window, and a user lacking ClockOwn:Mark
- [x] #10 The demo seeder gives employee@example.com a shift today with a colación window, so the mobile Maestro flow has something to assert against
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
1. Add App\Enums\PunchState (Before/Working/Done) derived from today's in/out marks — no break states (D-F1-a).
2. Extend MarkManager with getShiftAssignmentForDate() and getShiftDayForAssignment(), and refactor getShiftForDate() to compose them, so the aggregate reuses shift resolution instead of duplicating it.
3. Add App\Support\TodaySummary (readonly DTO: date, shift day, premise name, punch state, worked/contracted hours).
4. Add App\Http\Resources\TodayResource emitting naive wall-clock strings (Y-m-d, H:i:s) — never toIso8601String — with shift null when unscheduled and lunch both-or-neither.
5. Add App\Http\Controllers\Api\TodayController (invokable) + route GET /api/v1/me/today behind auth:sanctum with no permission middleware; punch omitted (null) for users without ClockOwn:Mark.
6. Week: worked_hours = sum of Workday.worked_time from Monday to today (one query, summed in PHP); contracted_hours = active assignment's shift.total_week_hours.
7. Seeder: give employee@example.com a dedicated all-week demo shift with a colacion window so the Maestro flow always has a shift today.
8. Pest feature test tests/Feature/Api/TodayApiTest.php covering 401, shift+colacion, no shift, no colacion window, missing ClockOwn:Mark, naive formats, week totals and constant query count.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Shipped GET /api/v1/me/today as an aggregate: TodayController (invokable) -> App\Support\TodaySummary -> TodayResource. Route sits behind auth:sanctum with no permission middleware; the punch block is null (not 403, not a fabricated 'before') for a user without ClockOwn:Mark, gated the same way DashboardController gates the web clock widget so the super-admin gate cannot answer for it.

Two incidental fixes the acceptance criteria required:
- ShiftDay's saving hook crashed on a non-free day with null colacion times (AC #4 could not otherwise be represented). It now subtracts nothing when either end is missing.
- MarkManager gained getShiftAssignmentForDate() and getShiftDayForAssignment(); getShiftForDate() composes them. No duplicated shift-resolution logic, and the aggregate can read the assignment's contracted weekly total on a free day.

week.contracted_hours reads Shift.total_week_hours off the assignment in force today (decision D-F1-d), which confirms PRD 11 open question #9 against a real column. week.worked_hours sums Workday.worked_time from Monday to today in PHP (at most seven rows) rather than with MySQL's TIME_TO_SEC.

Geofence block deliberately NOT added: Premise carries lat/lng but has no radius column, so a geofence here would be half a contract. Left to KMO-16 / decision D-F1-c.

Verified against the real seeded payload: {"date":"2026-08-04","shift":{"premise":"Sucursal Centro","start_time":"08:00:00","end_time":"17:00:00","lunch_start_time":"13:00:00","lunch_end_time":"14:00:00"},"punch":{"state":"done"},"week":{"worked_hours":7.88,"contracted_hours":40}}
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
GET /api/v1/me/today aggregates the mobile Marcaje tab into one request: TodayController -> App\Support\TodaySummary -> TodayResource, with every date and time a naive wall-clock string (Y-m-d, H:i:s) as kolvi-mobile's today-api.ts parser requires. shift is null when nothing is scheduled, the colacion window travels both ends or neither, punch.state is before/working/done derived from today's marks, and a user without ClockOwn:Mark gets 200 with the punch block null rather than a 403. Verified by 17 Pest tests in tests/Feature/Api/TodayApiTest.php (covering 401, shift with colacion, no shift, no colacion window, ended assignment, all three punch states, week totals and a constant query count) and by rendering the real seeded payload for employee@example.com, whom ShiftSeeder now gives a five-day 08:00-17:00 shift with a 13:00-14:00 colacion starting on the day the seeder runs. Full suite 677 passed / 4 skipped, Pint clean; no TypeScript touched.
<!-- SECTION:FINAL_SUMMARY:END -->
