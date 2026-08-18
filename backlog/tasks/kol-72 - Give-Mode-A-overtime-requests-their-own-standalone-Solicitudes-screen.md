---
id: KOL-72
title: Give Mode A overtime requests their own standalone Solicitudes screen
status: Done
assignee: []
created_date: '2026-08-17 19:08'
updated_date: '2026-08-18 10:56'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-45
  - KOL-71
ordinal: 50000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Mode A overtime requests (an employee asking in advance to work overtime) are decided today in a tab of /overtime/queue, alongside the post-hoc excess approval that KOL-71 moves onto Jornadas. A request isn't tied to a computed Workday until the day is actually worked and calculated, so it doesn't belong on Jornadas either — it needs its own standalone screen under the Horas extra section, extracted from the queue rather than left stranded when the queue is decommissioned in KOL-74.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A supervisor or admin can list pending (and, via a status filter, decided) Mode A requests for their team/organization on a dedicated screen reachable from the Horas extra hub, independent of Jornadas and independent of the old queue
- [x] #2 A request can be approved or rejected from this screen with the same notification behaviour as today (OvertimeRequestApproved/OvertimeRequestRejected)
- [x] #3 Gating matches what the queue's Solicitudes tab enforced: reachable only when the tenant's authorisation mode allows requests, visible to ViewTeam/Manage, decidable only by ApproveTeam/Manage for one's own team
- [x] #4 Pest tests cover listing, approving, rejecting, the mode-disabled 404, and cross-team refusal, exercised through the new standalone route rather than the queue
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
1. OvertimeRequestPolicy: add viewTeam() (ViewTeam or Manage:OvertimeAuthorization), mirroring OvertimeAuthorizationPolicy::viewTeam().
2. New App\Http\Controllers\OvertimeRequestController (top-level, distinct from My\OvertimeRequestController): index() (Gate viewTeam, 404 if mode disallows requests, status-tab filter defaulting to pending, org/team-scoped list, can.decide), approve()/reject() moved from OvertimeQueueController::approveRequest/rejectRequest.
3. Routes: new overtime/requests prefix (name overtime.requests.) under permission:ViewTeam:OvertimeAuthorization|Manage:OvertimeAuthorization, index/approve/reject. Leave the old overtime/queue Solicitudes tab and routes untouched (KOL-74 removes them), matching KOL-71's coexistence precedent.
4. OvertimeController::index: add can.viewRequests (mode allows requests && ViewTeam/Manage). overtime/index.tsx: add a "Solicitudes de horas extra" link gated on can.viewRequests.
5. New page resources/js/pages/overtime/requests/index.tsx: status tabs, DataTable (employee/date/requested_hours/reason/status/reviewed_by/actions), approve + reject dialogs — mirrors the queue's Solicitudes tab UI, standalone.
6. Lang (es/en) under ui.overtime.requests.review.*.
7. Wayfinder regenerate (npm run build/dev) for the new controller/routes.
8. Pest: tests/Feature/OvertimeRequestReviewTest.php — listing, approve, reject (+ reason required), mode-disabled 404, cross-team refusal, admin decides any team, decided-request status filter.
9. pint --dirty, targeted Pest run, tsc --noEmit.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Additional scope folded in per user request during review: relabeled and repointed the pre-existing sidebar 'Aprobaciones' nav item from /overtime/queue ('Cola de horas extra') to the new /overtime/requests screen, labeled 'Horas extra pendientes' (es) / 'Pending overtime requests' (en) — lang key renamed nav.overtime_queue -> nav.overtime_requests.

Renamed HandleInertiaRequests::pendingOvertimeCount() -> pendingOvertimeRequestsCount(): it previously combined pending OvertimeAuthorization + OvertimeRequest counts (KOL-66); now it counts only pending OvertimeRequest rows, matching what the badge's destination (the new Solicitudes screen) actually shows. Renamed the shared Inertia prop and resources/js/types/auth.ts field to match. Rewrote tests/Feature/OvertimeQueueBadgeTest.php for the new semantics (6 tests, all passing) and updated a stale comment in OvertimeQueueTest.php.

Also reordered the old queue page's top-level tabs (resources/js/pages/overtime/queue/index.tsx) so Solicitudes renders before Jornadas, per user request.

Full Overtime* suite (199 tests) still green; pint clean; tsc clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Extracted Mode A overtime requests into a standalone /overtime/requests screen (OvertimeRequestController + resources/js/pages/overtime/requests/index.tsx), reachable from the Horas extra hub, gated by ViewTeam/Manage:OvertimeAuthorization to view and ApproveTeam/Manage for one's own team to decide, 404ing under pure post-hoc mode. Old /overtime/queue Solicitudes tab left untouched, coexisting until KOL-74. Per user request during review, also relabeled/repointed the sidebar 'Aprobaciones' nav item to the new screen ('Horas extra pendientes'), narrowed its badge (renamed pendingOvertimeCount -> pendingOvertimeRequestsCount) to count only pending requests instead of combined Jornadas+requests, and reordered the old queue's tabs (Solicitudes before Jornadas). Verified via tests/Feature/OvertimeRequestReviewTest.php (9 tests) and a rewritten tests/Feature/OvertimeQueueBadgeTest.php (6 tests); full Overtime* suite (199 tests, 886 assertions) passing; pint clean; tsc --noEmit clean; npm run build succeeds.
<!-- SECTION:FINAL_SUMMARY:END -->
