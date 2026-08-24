---
id: KOL-83
title: 'Leave request options, business-days and store for the mobile app'
status: To Do
assignee: []
created_date: '2026-08-24 09:51'
labels:
  - mobile-api
dependencies: []
references:
  - kolvi-mobile KMO-41
  - kolvi-mobile src/features/permisos/leave-options-api.ts
documentation:
  - docs/prd-mobile-app.md
priority: medium
ordinal: 61000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile's request wizard (KMO-41: KMO-41.1 done, KMO-41.2/.3 next) needs three more leave endpoints on the mobile API: the self-service type list for step 1, the server-computed business-day count for the review step, and creating the request itself. My\LeaveController already has all three for the web self-service portal (create() providing typeOptions/halfDayTypeOptions, businessDays(), store()); this ports them to /api/v1 the way KOL-64/65/68/81 ported the other Jornada and Permisos mobile-API endpoints. Confirmed missing today: none of GET /api/v1/me/leaves/options, GET /api/v1/me/leaves/business-days or POST /api/v1/me/leaves exist on Api\LeavesController, which today only has index()/destroy() (KOL-81). kolvi-mobile's KMO-41.1 client (leave-options-api.ts) was already built against this ticket's own reading of docs/prd-mobile-app.md §F5 and LeaveType::selfServiceOptions() and will need adjusting wherever the real implementation disagrees.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 GET /api/v1/me/leaves/options returns {data: [{value, label}, ...]} from LeaveType::selfServiceOptions() (never includes Medical) for the authenticated employee, gated on permission:RequestOwn:Leave — matches the envelope kolvi-mobile's leave-options-api.ts already parses
- [ ] #2 The options response also carries the half-day types (LeaveHalfDayType::options()) alongside the leave types, matching PRD §F5's 'self-service types + half-day types' bundling; kolvi-mobile does not consume this field yet, so its exact key name is this ticket's call
- [ ] #3 GET /api/v1/me/leaves/business-days accepts start_date/end_date, returns {business_days} computed by BusinessDaysCalculator for the authenticated employee, gated on permission:RequestOwn:Leave — mirrors My\LeaveController::businessDays() exactly
- [ ] #4 POST /api/v1/me/leaves creates a leave (type, start_date, end_date, half_day, half_day_type, notes) with the same validation My\LeaveController::store() runs, gated on permission:RequestOwn:Leave
- [ ] #5 Medical leave (LeaveType::Medical) is refused by the store validation, the same way the web form's Rule::enum(LeaveType::class)->except([LeaveType::Medical]) refuses it
- [ ] #6 A half-day request is confined to a single day (start_date === end_date) and always stores business_days_requested = 0.5, mirroring store()'s own half-day branch
- [ ] #7 A submitted leave notifies the approvers LeaveApprovers resolves (Notification::send(..., LeaveRequestSubmitted)), mirroring store()'s own notification
- [ ] #8 OPEN DECISION, do not assume it away: confirm or adjust kolvi-mobile's guessed wire shapes (leave-options-api.ts's {data: [...]} envelope for options; kolvi-mobile KMO-41.3 will read {business_days} and whatever POST /me/leaves answers with) — this parser is the one place kolvi-mobile changes if the real implementation disagrees
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
