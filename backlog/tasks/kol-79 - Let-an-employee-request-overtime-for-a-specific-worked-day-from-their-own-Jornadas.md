---
id: KOL-79
title: >-
  Let an employee request overtime for a specific worked day from their own
  Jornadas
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-08-18 10:29'
updated_date: '2026-09-13 10:38'
labels:
  - overtime
  - frontend
  - backend
milestone: m-2
dependencies:
  - KOL-45
ordinal: 57000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Today an employee has two disconnected ways to interact with overtime: the Mode A request flow (My\OvertimeRequestController::create/store, resources/js/pages/my/overtime-requests/create.tsx) where they free-type a date and hours, and their own read-only Jornadas (My\WorkdayController::index/show, resources/js/pages/my/workdays/), which never shows the day's calculated overtime at all — WorkdayPresenter::workday() (used by the employee's own show page) omits calculated_overtime; only WorkdayPresenter::overtime() (used by the admin/supervisor Jornadas page, KOL-71) exposes it.

The ask: from a specific day on the employee's own Workday index (or its detail page), when that day already carries calculated overtime, let the employee submit an overtime request for it without retyping the hours — the form takes calculated_overtime from that Workday and submits it as the requested_hours, rather than the employee guessing or recalculating what they worked.

This should reuse the existing Mode A request machinery (My\OvertimeRequestController::store, the retroactive-window validation from KOL-45, and the tenant mode gating via OrganizationSettings::overtimeAuthorizationMode()->allowsRequests()) rather than duplicating that logic — the new piece is a form pre-filled from a specific Workday's already-computed figure, and exposing that figure to the employee in the first place.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The employee's own Workday index or detail page shows, for a day with calculated overtime, an action to request overtime for that day
- [x] #2 Submitting it creates an overtime request dated to that workday, with requested_hours equal to the workday's calculated_overtime — the employee is not required to type the hours
- [x] #3 The action does not appear for a day with no calculated overtime, and is hidden entirely under pure post-hoc mode, mirroring the existing 'Solicitar horas extra' gating
- [x] #4 A day outside the tenant's retroactive request window is refused with the same Spanish message the manual request flow (KOL-45) already gives
- [x] #5 The employee can still add an optional reason before submitting
- [x] #6 The created request appears on 'Mis solicitudes' like any other request
- [x] #7 Pest tests cover: requesting from a day with calculated overtime succeeds with matching hours, a day with zero calculated overtime is refused, and a day outside the retroactive window is refused with the existing message
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
1. WorkdayPresenter::workday() - add calculated_overtime (trimmed H:i) to the returned array.
2. My\WorkdayController::show() - inject OrganizationSettings, compute canRequestOvertime (mode allows requests && Gate::allows('create', OvertimeRequest::class) && workday has calculated_overtime > 0), pass as prop.
3. My\OvertimeRequestController::create() - accept optional ?workday= query int; when it resolves to an owned Workday with calculated_overtime, pass a 'prefill' prop (workday_id, date, hours); otherwise null (silent fallback to blank form).
4. My\OvertimeRequestController::store() - accept optional workday_id; when present, load the owned Workday (404 if not found/not owned), refuse via ValidationException if it has no calculated_overtime, and force requested_hours from that workday's calculated_overtime (ignoring whatever was posted) so the stored hours are guaranteed to match, not just UI convention. Retroactive-window/date validation is unchanged and applies automatically.
5. Frontend components/workday-detail.tsx - add calculated_overtime to WorkdayDetailData, add optional overtimeRequest prop {hours, href} rendering a card + 'Solicitar horas extra' button/link (employee-only; admin keeps its existing overtime decide/revoke section, mutually exclusive with this new prop).
6. Frontend pages/my/workdays/show.tsx - build the href via routes/my/overtime-requests create({query:{workday: workday.id}}), pass overtimeRequest to WorkdayDetail when canRequestOvertime && workday.calculated_overtime.
7. Frontend pages/my/overtime-requests/create.tsx - accept prefill prop; when present, seed date/requested_hours (converted via timeToDecimalHours) and workday_id, disable date/hours inputs, adjust hint copy; reason stays editable.
8. Lang es/en ui.php - add workdays.show.overtime.request button label + a from-workday hint key under overtime.requests.my.form, plus overtime.requests.validation.no_calculated_overtime.
9. Pest tests: My\WorkdayTest (canRequestOvertime prop true/false cases) and My\OvertimeRequestTest (create() prefill via workday query param; store() with workday_id succeeds with matching hours regardless of posted hours; zero-calculated-overtime workday refused; workday outside retroactive window refused with existing KOL-45 message; ownership enforced).
10. Run pint --dirty, sa test --compact filtered, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented: WorkdayPresenter::workday() now exposes calculated_overtime; My\WorkdayController::show() computes canRequestOvertime (mode allows requests + Gate::allows('create', OvertimeRequest::class) + day has calculated overtime); My\OvertimeRequestController::create()/store() accept an optional workday query/field to prefill and then strictly re-derive requested_hours from the Workday server-side, ignoring any posted value. Frontend: workday-detail.tsx gained an overtimeRequest card (employee-only, mutually exclusive with the admin overtime decide/revoke section); my/workdays/show.tsx wires it via routes/my/overtime-requests create({query:{workday}}); create.tsx pre-fills and disables date/hours when reached from a workday, reason stays editable. Filtered tests (Workday: 132, My.OvertimeRequestTest: 25) pass; pint clean; npm run types:check has no new errors (2 pre-existing unrelated errors in roles/index.tsx and roles/show.tsx, confirmed present on master too). Full suite not run per project convention (only after user review).

Verified in browser by the user (workday #125, employee@example.com/bradtke.caitlyn login, org 1 Combined mode, 7-day window): the request form pre-fills date/hours from the workday and disables them, submits, and lands on Mis solicitudes. Fixed a layout bug found during that review: the from-workday hint attached only to the date field made the two fields different heights, and the grid's default stretch pushed the hours input down — fixed by moving the hint to a shared line below both fields and adding items-start to the grid. Full suite: 1424 passed, 7 skipped, 0 failed (./vendor/bin/sail artisan test --compact).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Employees can now request overtime directly from a specific worked day on their own Jornadas detail page: a 'Solicitar horas extra' card appears when the day carries calculated overtime (hidden under post-hoc mode or when there's nothing to request), and the resulting request form is pre-filled with that day's date and hours, disabled to prevent editing. The backend re-derives requested_hours from the Workday itself server-side, so the stored figure always matches what was calculated regardless of what the client posts. Reuses the existing Mode A store()/retroactive-window validation from KOL-45 unchanged. Verified with 7 new Pest tests plus the full suite (1424 passed / 7 skipped / 0 failed) and manual browser review.
<!-- SECTION:FINAL_SUMMARY:END -->
