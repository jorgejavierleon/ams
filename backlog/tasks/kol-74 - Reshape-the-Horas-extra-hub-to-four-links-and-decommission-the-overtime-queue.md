---
id: KOL-74
title: Reshape the Horas extra hub to three links and decommission the overtime queue
status: Done
assignee: []
created_date: '2026-08-17 19:08'
updated_date: '2026-08-22 12:01'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-71
  - KOL-72
ordinal: 52000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Final step of the Jornadas-overtime refactor: once approval lives on Jornadas (KOL-71) and Mode A requests have their own screen (KOL-72), the Horas extra hub and the old combined queue are cleaned up. The hub becomes exactly three links: Solicitudes, Pactos, Saldo de descanso. The old /overtime/queue screen, its controller, its routes and its tests are removed. (KOL-73, the approved-overtime ledger, was dropped per user decision — no longer a dependency or a fourth hub link.)
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The Horas extra hub (/overtime) shows exactly three links, gated by the same permissions their destination screens already enforce: Solicitudes, Pactos, Saldo de descanso
- [x] #2 /overtime/queue and its controller actions for post-hoc approve/object/bulk-decide no longer exist; the route returns not-found
- [x] #3 The sidebar link and pending-review badge added for the old queue now point at Jornadas (or wherever pending overtime is now surfaced) instead of a dead route
- [x] #4 KOL-67 is amended or closed as superseded, since its premise (a direct link to the queue) no longer applies
- [x] #5 No test file still exercises the removed queue routes; the superseded test files are deleted, not left failing
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
1. Backend: delete app/Http/Controllers/OvertimeQueueController.php; remove the overtime.queue.* route group (routes/web.php ~208-223) and its OvertimeQueueController use import; remove OvertimeController::index's viewQueue key (+docblock mention).
2. Fix a real dead-link bug this ticket surfaces: app/Notifications/OvertimeRequestSubmitted.php still routes reviewers to route('overtime.queue.index'); repoint to route('overtime.requests.index') (KOL-72's replacement, same no-param shape). Add a Pest test asserting the mail's action URL.
3. Frontend: resources/js/pages/overtime/index.tsx - drop the queueIndex import and can.viewQueue button, leaving exactly 3 link categories (Solicitudes, Pactos, Saldo de descanso). Delete resources/js/pages/overtime/queue/ (self-contained, no shared component deps). Regenerate Wayfinder (npm run build) to drop the stale resources/js/routes/overtime/queue/* files.
4. Lang: remove the queue block from lang/en/ui.php and lang/es/ui.php (~1911-2017 en, mirrored in es) - fully owned by the deleted screen, unused elsewhere (KOL-71/72 built separate ui.workdays.*/ui.overtime.requests.review.* keys).
5. Tests: delete tests/Feature/OvertimeQueueTest.php and tests/Feature/OvertimeQueueRequestsTest.php (superseded by WorkdayOvertimeTest and OvertimeRequestReviewTest per KOL-71/72). Confirm workdays.overtime.bulk-decide (WorkdayController::bulkDecideOvertime) is the real successor for the queue's bulkDecide before deleting it (confirmed via route:list).
6. KOL-67: archive as superseded (same pattern as KOL-73) with an explanatory comment - its premise (a direct queue link) is gone now that the queue no longer exists; the admin-Pactos-link half and any supervisor-Jornadas-link redesign are new scope, not part of KOL-74.
7. Verify: grep for any remaining overtime.queue/OvertimeQueueController references; pint --dirty; sa test --compact (targeted then broader); npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Explore agent surveyed hub, queue controller/page, tests, and nav/badge wiring before starting. Found nav+badge were already fully repointed to /overtime/requests by KOL-72 (AC #3 pre-satisfied, will verify not regress). Found and will fix a real bug: OvertimeRequestSubmitted notification still links to the dead queue route.

Implementation complete. Backend: deleted OvertimeQueueController + its 5 routes; trimmed OvertimeController::index (dropped viewQueue); fixed a real dead-link bug found along the way (OvertimeRequestSubmitted notification still routed reviewers to the removed overtime.queue.index — repointed to overtime.requests.index, covered by a new Pest test). Frontend: hub (overtime/index.tsx) now renders exactly 3 link categories (Solicitudes, Pactos, Saldo de descanso) after dropping the queue button/import; deleted resources/js/pages/overtime/queue/ entirely (self-contained, no shared-dialog deps to preserve). Confirmed workdays.overtime.bulk-decide (WorkdayController::bulkDecideOvertime, from KOL-71) is the real successor to the queue's bulkDecide before deleting it. Lang: removed the now-orphaned 'queue' block from lang/en/ui.php and lang/es/ui.php. Tests: deleted OvertimeQueueTest.php and OvertimeQueueRequestsTest.php (superseded per KOL-71/72 notes); added OvertimeRequestSubmittedNotificationTest.php. Sidebar link + pending badge were already fully repointed to /overtime/requests by KOL-72 (verified via OvertimeQueueBadgeTest, still green) - AC #3 was pre-satisfied, no code change needed there. KOL-67 archived as superseded (own comment already flagged its premise was gone; the admin-Pactos-link half is new scope, not implemented here). Verified: pint clean; wayfinder:generate produces no diff (no stale queue route helpers existed); tsc --noEmit clean on touched files (2 pre-existing unrelated errors in roles/index.tsx and roles/show.tsx confirmed present on master via git stash, not introduced by this change); Overtime+Workday Pest suites 275/275 passing (1144 assertions); curl confirms GET /overtime/queue now 404s. Could not drive a live browser check - Chrome extension not connected in this session - so the hub's visual rendering (3 buttons, correct labels/permissions) still needs a human look.

Follow-up fix per user review feedback (2026-08-22): the hub's subtitle still said 'Gestión de horas extraordinarias, sus autorizaciones y pactos' (es) / 'Manage overtime authorizations and pactos' (en), left over from before the queue/authorizations were pulled off this page. Reworded both to reflect the actual 3-link hub scope: es 'Solicitudes de horas extra, pactos y saldo de descanso compensatorio.', en 'Overtime requests, pactos and rest-day compensation balances.' pint clean; no test asserts on this string.

Full suite run for finalization: 1098 tests, 1090 passed, 1 failed, 7 skipped. The 1 failure (Tests\Feature\Api\UpcomingShiftsApiTest::the_days_param_is_capped_at_30) is a pre-existing date-boundary flake in an unrelated Shifts API test, confirmed present on unmodified master via git stash comparison -- not caused by this branch. All Overtime/Workday-related tests pass (275/275).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Reshaped the Horas extra hub to 3 links (Solicitudes, Pactos, Saldo de descanso) and decommissioned /overtime/queue: deleted OvertimeQueueController and its routes, the queue's React page, and its two Pest test files (superseded by KOL-71/72's WorkdayOvertimeTest and OvertimeRequestReviewTest); trimmed OvertimeController::index and the hub page. Along the way, fixed a real dead-link bug the removal would otherwise have exposed later: the OvertimeRequestSubmitted mail notification still pointed reviewers at the removed overtime.queue.index route, repointed to overtime.requests.index (new Pest test covers it). Reworded the hub's stale subtitle (still described the removed queue/authorizations) per user review feedback. Confirmed workdays.overtime.bulk-decide is the real KOL-71 successor to the queue's bulk-approve before deleting it. Sidebar link and pending badge were already repointed to /overtime/requests by KOL-72 (verified via OvertimeQueueBadgeTest, unchanged). KOL-67 archived as superseded, its own comment having already flagged its premise (a direct queue link) as gone; KOL-73 (the approved-overtime ledger) was dropped per user decision, so the hub is 3 links rather than the original 4. Verified: pint clean; 275 Overtime/Workday Pest tests green; tsc --noEmit clean on touched files; curl confirms GET /overtime/queue now 404s; user visually confirmed the hub in browser.
<!-- SECTION:FINAL_SUMMARY:END -->
