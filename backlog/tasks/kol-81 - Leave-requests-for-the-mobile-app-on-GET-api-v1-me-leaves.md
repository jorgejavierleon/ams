---
id: KOL-81
title: Leave requests for the mobile app on GET /api/v1/me/leaves
status: Done
assignee: []
created_date: '2026-08-19 10:03'
updated_date: '2026-08-23 16:05'
labels:
  - mobile-api
dependencies: []
references:
  - kolvi-mobile KMO-39
  - kolvi-mobile src/features/permisos/leaves-api.ts
priority: medium
ordinal: 59000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-39 (Permisos . Mis solicitudes) needs the employee's own leave requests plus their vacation balance, and a way to cancel a pending one. My\LeaveController already has this whole flow for the web self-service portal (index() with its status/date filters and vacationBalance(), destroy() with LeaveManager::delete()); this ports it to /api/v1 the way KOL-64/65/68/69 ported the other Jornada and mobile-API endpoints. Confirmed missing today: /api/v1/me/leaves and /api/v1/me/leaves/{id} both 404 on a live Sail instance, unlike /me/workdays and /me/mark-modifications which 401 (exist, gated on auth) - kolvi-mobile's client (leaves-api.ts) was built against this ticket's own reading of the contract below and will need adjusting wherever the real implementation disagrees.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/v1/me/leaves returns the authenticated employee's own leaves, scoped to user_id, gated on permission:ViewOwn:Leave
- [x] #2 Each entry carries id, type_label (Leave::type->label(), never the raw enum - kolvi-mobile never hardcodes leave types), status (pending|approved|rejected), status_label, status_badge, start_date and end_date as Y-m-d
- [x] #3 status_badge comes from a new LeaveStatus::badge() method mirroring WorkdayStatus::badge(): pending -> warning, approved -> success, rejected -> destructive, matching docs/design-decisions.md D-F5 on the kolvi-mobile side
- [x] #4 The response also carries vacationBalance ({used, available, total}), reusing My\LeaveController::vacationBalance()'s exact query, alongside the list rather than requiring a second request
- [x] #5 The list is a bare array wrapped with vacationBalance via ->additional() (LeaveResource::collection(...)->additional(['vacationBalance' => ...])), i.e. {data: [...], vacationBalance: {...}} - not Laravel's default paginator envelope; no pagination parameter, matching how KOL-64/65/68/69 chose not to paginate the other /me/* mobile lists even though My\LeaveController::index paginates(15) for the web table
- [x] #6 Results are ordered the same way My\LeaveController::index defaults (start_date desc); an employee with no leaves gets an empty array with vacationBalance still present
- [x] #7 DELETE /api/v1/me/leaves/{leave} cancels through LeaveManager::delete() when the leave belongs to the authenticated employee and is still status=pending (mirrors My\LeaveController::destroy's abort_unless guard exactly), gated on permission:CancelOwn:Leave
- [x] #8 Cancelling a leave that is not pending, or does not belong to the authenticated employee, 403s or 404s rather than deleting it
- [x] #9 OPEN DECISION, do not assume it away: there is no approver-note / rejection-reason column on Leave today - only notes (the requester's own, set at creation) and approved_by (the approver's user id). The design mock (kolvi-mobile KMO-39's References) and docs/design-decisions.md D-F5-d both assume a distinct approver note like 'Cupo mensual excedido' shown on a rejected or approved row, which nothing currently stores. Resolve before or during implementation: add a column (e.g. a rejection_reason on reject()), or confirm v1 ships with no approver note and kolvi-mobile drops that part of its own AC #1
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
1. Add rejection_reason (nullable string) migration on leaves + Leave model/factory. 2. LeaveManager::reject() accepts optional reason, stores it; approve() clears it. 3. Web LeaveController::reject() validates+passes reason (required, like OvertimeRequestController); leaves/index.tsx reject dialog gets a Textarea like overtime/requests/index.tsx; view-detail dialog shows it when rejected; rejected mail shows it when present. 4. LeaveStatus::badge() mirroring WorkdayStatus::badge() (pending->warning, approved->success, rejected->destructive). 5. New Api\LeavesController (index via LeaveResource::collection()->additional(['vacationBalance'=>...]), destroy mirroring My\LeaveController::destroy's abort_unless guard) + LeaveResource (id, type_label, status, status_label, status_badge, start_date, end_date, approver_note=rejection_reason). 6. Routes: GET/DELETE /api/v1/me/leaves(/{leave}) gated permission:ViewOwn:Leave / CancelOwn:Leave. 7. Pest tests: LeavesApiTest (mirroring PendingMarkModificationsApiTest) + reject-reason coverage in LeaveManagementTest/SupervisorLeaveApprovalTest. 8. pint --dirty, sa test --compact, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Resolved AC#9 (user decision): added leaves.rejection_reason column + LeaveManager reject(reason)/approve clears it; web LeaveController::reject now requires 'reason' and leaves/index.tsx reject dialog gained a textarea (mirrors overtime/requests/index.tsx pattern); exposed as approver_note on the mobile LeaveResource. New Api\LeavesController (index via ->additional(), destroy mirroring My\LeaveController::destroy's abort_unless) + routes gated permission:ViewOwn:Leave/CancelOwn:Leave. LeaveStatus::badge() added mirroring WorkdayStatus::badge(). 12 new tests in LeavesApiTest, plus reject-reason coverage added to LeaveManagementTest/SupervisorLeaveApprovalTest/LeaveWorkdayRecalculationTest. Full suite 1111/1118 (7 pre-existing skips), pint/phpstan/eslint/prettier/tsc all clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
GET/DELETE /api/v1/me/leaves(/{leave}) ported My\LeaveController's self-service leave list, vacation balance and cancel flow to the mobile API, gated on ViewOwn:Leave/CancelOwn:Leave. Resolved the open approver-note decision by adding a rejection_reason column: LeaveManager::reject() now takes a reason (cleared on re-approval), the web reject dialog requires one, and the mobile LeaveResource exposes it as approver_note. Added LeaveStatus::badge().
<!-- SECTION:FINAL_SUMMARY:END -->
