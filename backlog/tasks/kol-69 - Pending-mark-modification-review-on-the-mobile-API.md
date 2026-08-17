---
id: KOL-69
title: Pending mark-modification review on the mobile API
status: Done
assignee: []
created_date: '2026-08-17 12:32'
updated_date: '2026-08-17 19:13'
labels:
  - mobile-api
dependencies: []
references:
  - kolvi-mobile KMO-35
priority: medium
ordinal: 48000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-35 needs the Jornada tab's pending-correction card: the employee's own pending mark-modification requests, with approve/decline actions honoring the review window. My\WorkdayController already has this whole flow for the web self-service portal (the pending list in index(), approveModification()/declineModification(), MarkModificationManager::approve/decline, MarkModification::isActionable()); this ports it to /api/v1 the way KOL-64/65/68 ported the other Jornada endpoints.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/v1/me/mark-modifications returns the authenticated employee's own pending mark-modification requests, gated by permission:ReviewOwn:MarkModification and scoped to user_id
- [x] #2 Each entry carries workday_id, modification id, mark_type_label, original_time and proposed_time as HH:mm (matching the me/workdays time convention), reason label, requested_by name, and expires_at as a naive datetime (the moment isExpired() closes the window) so the client renders its own countdown rather than a server-baked string
- [x] #3 Results are ordered newest-first by created_at, matching My\WorkdayController::index, and a bare JSON array (matching me/workdays and me/marks); an employee with none gets an empty array
- [x] #4 POST /api/v1/me/workdays/{workday}/modifications/{modification}/approve approves the modification through MarkModificationManager::approve when it is isActionable() and both the workday and modification belong to the authenticated employee, otherwise 403
- [x] #5 POST /api/v1/me/workdays/{workday}/modifications/{modification}/decline declines the modification through MarkModificationManager::decline under the same ownership and isActionable() guard
- [x] #6 Acting on an already-reviewed or expired modification is rejected server-side (never trusts the client's own actionable check)
- [x] #7 Another employee's workday or modification id 404s or 403s rather than exposing or mutating it
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. app/Http/Resources/PendingMarkModificationResource.php — new resource: id, workday_id, mark_type_label, original_time/proposed_time (H:i, mirroring WorkdayResource's trim convention), reason label, requested_by name, expires_at (Y-m-d H:i:s, from MarkModification::reviewWindowStartedAt()->addHours(config('ams.mark_modification_timeout_hours')))
2. app/Http/Controllers/Api/PendingMarkModificationsController.php — index(): MarkModification::query()->where('user_id', $user->id)->where('status', Pending)->with(['mark','workday:id,date','createdBy:id,name'])->latest('created_at')->get(), returns PendingMarkModificationResource::collection (bare array, matching WorkdayResource); approve()/decline(): mirror My\WorkdayController::approveModification/declineModification's authorizeReview() (ownership + isActionable(), abort 403) then call MarkModificationManager::approve()/decline()
3. routes/api.php — GET me/mark-modifications, POST me/workdays/{workday}/modifications/{markModification}/approve and .../decline, all permission:ReviewOwn:MarkModification, the two POSTs with ->scopeBindings() matching routes/web.php's My routes
4. tests/Feature/Api/PendingMarkModificationsApiTest.php — one Pest file covering every AC: list shape, ordering, empty array, scoping to the authenticated user, approve/decline success, 403 on another employee's workday/modification, 403 on an already-reviewed or expired modification
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Ported My\WorkdayController's pending-modification list and approve/decline to /api/v1: PendingMarkModificationsController (index/approve/decline), PendingMarkModificationResource, three new routes gated permission:ReviewOwn:MarkModification. tests/Feature/Api/PendingMarkModificationsApiTest.php covers all 7 ACs (12 tests, 12 passing). pint clean. Full suite not run yet per user instruction — only after feature is verified.

Full suite: 1059/1059 passing (4 pre-existing skips), no regressions.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Ported My\WorkdayController's pending-modification list and approve/decline to /api/v1 (PendingMarkModificationsController, PendingMarkModificationResource, three routes gated permission:ReviewOwn:MarkModification), for kolvi-mobile KMO-35's pending-correction card. Verified with tests/Feature/Api/PendingMarkModificationsApiTest.php (12 tests) plus the full suite (1059/1059).
<!-- SECTION:FINAL_SUMMARY:END -->
