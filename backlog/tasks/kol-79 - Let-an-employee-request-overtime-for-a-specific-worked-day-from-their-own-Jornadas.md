---
id: KOL-79
title: >-
  Let an employee request overtime for a specific worked day from their own
  Jornadas
status: To Do
assignee: []
created_date: '2026-08-18 10:29'
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
- [ ] #1 The employee's own Workday index or detail page shows, for a day with calculated overtime, an action to request overtime for that day
- [ ] #2 Submitting it creates an overtime request dated to that workday, with requested_hours equal to the workday's calculated_overtime — the employee is not required to type the hours
- [ ] #3 The action does not appear for a day with no calculated overtime, and is hidden entirely under pure post-hoc mode, mirroring the existing 'Solicitar horas extra' gating
- [ ] #4 A day outside the tenant's retroactive request window is refused with the same Spanish message the manual request flow (KOL-45) already gives
- [ ] #5 The employee can still add an optional reason before submitting
- [ ] #6 The created request appears on 'Mis solicitudes' like any other request
- [ ] #7 Pest tests cover: requesting from a day with calculated overtime succeeds with matching hours, a day with zero calculated overtime is refused, and a day outside the retroactive window is refused with the existing message
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
