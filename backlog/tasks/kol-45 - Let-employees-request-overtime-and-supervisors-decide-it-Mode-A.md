---
id: KOL-45
title: Let employees request overtime and supervisors decide it (Mode A)
status: Done
assignee: []
created_date: '2026-08-06 02:53'
updated_date: '2026-08-16 01:51'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-11
  - KOL-37
  - KOL-43
  - KOL-44
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 1000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Mode A from PRD section 7.1, the Talana-shaped flow: the employee requests the overtime *before* working it, the supervisor approves or rejects, and only then does the system count it. The PRD calls this the most airtight mode against marking errors, because an unrequested excess never had anyone intending it.

Scope of the request:
- An employee requests hours for a date — same day, a future date, or a past date when the tenant allows it. The retroactive window is the tenant setting from KOL-37; a request outside it is refused with a Spanish message stating the window.
- The request carries the hours asked for and, optionally, why.
- The supervisor approves or rejects it, and the employee sees the outcome and their own history.
- A rejected or unanswered request does not stop the employee from working — it stops those hours from being payable, which is the entire point.

This is web console only for now. The mobile app ships on its own release cycle and its `/api/v1` contract is better added once the domain has settled; a follow-up task covers that.

The employee-facing side mirrors the leave request flow closely enough to copy its shape: `app/Http/Controllers/My/LeaveController.php`, the routes under the `my` prefix in `routes/web.php` gated by `permission:RequestOwn:Leave`, and `resources/js/pages/my/leaves`. The supervisor side lands in the same queue as KOL-44 rather than a second inbox — a supervisor should have one place to look, whether the hours arrived as a request or as an unrequested excess.

Only relevant when the tenant runs pre-authorisation or combined mode; under pure post-hoc the request UI is not shown at all.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 An employee can request overtime hours for a given date, with an optional reason, from the web console in Spanish
- [x] #2 Requests are accepted for the same day and future dates, and for past dates only within the tenant retroactive window; a request outside the window is refused with a message stating the window
- [x] #3 The supervisor decides requests in the same queue used for unrequested excess, not in a separate inbox
- [x] #4 The employee sees their own request history and current status, and no one else requests
- [x] #5 A request that is rejected or never answered never produces payable hours, and never prevents the employee from working or marking
- [x] #6 The request UI is hidden entirely for tenants running pure post-hoc mode
- [x] #7 Requests are organization-scoped and an employee can only request for themselves
- [x] #8 Pest tests cover a same-day request, a future request, a retroactive request inside and outside the window, a rejection, an unanswered request producing no payable hours, and the UI being absent under post-hoc mode
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
1. Backend domain: migration `overtime_requests` (organization_id, user_id, date, requested_hours time, reason text nullable, status, reviewed_by, reviewed_at, decision_reason text nullable, timestamps); `OvertimeRequestStatus` enum (Pending/Approved/Rejected, label/badge/options mirroring OvertimeAuthorizationStatus); `OvertimeRequest` model (BelongsToOrganization, approve()/reject() methods requiring a reviewer, reject() requires decision_reason); factory.
2. OrganizationSettings: add `overtimeRetroactiveRequestDays()` accessor reading `overtime_retroactive_request_days` (default 7).
3. Policy `OvertimeRequestPolicy`: create (RequestOwn:OvertimeAuthorization, self), decide (ApproveTeam:OvertimeAuthorization scoped to supervisor's direct reports, mirrors OvertimeAuthorizationPolicy::canDecide).
4. Notifications: OvertimeRequestSubmitted, OvertimeRequestApproved, OvertimeRequestRejected (mirror Leave's). Reuse LeaveApprovers-style resolution via a small OvertimeRequestApprovers service (supervisor if ApproveTeam:OvertimeAuthorization else admins).
5. Employee controller `App\Http\Controllers\My\OvertimeRequestController` (index/create/store), mirroring My\LeaveController: validate date (today/future always ok; past only within tenant retroactive window, Spanish error naming the window), optional reason, hours as H:i. abort 404/403 when tenant mode is PostHoc (OrganizationSettings::overtimeAuthorizationMode()->allowsRequests() === false).
6. Routes: `my/overtime-requests` (index, create, store) under RequestOwn:OvertimeAuthorization/ViewOwn:OvertimeAuthorization, in routes/web.php `my` group.
7. Supervisor decision: add approve/reject actions to existing OvertimeQueueController (or a thin sibling), new routes under `overtime/queue/requests/{overtimeRequest}/approve|reject`. Queue index() gains a `requests` prop (pending OvertimeRequest list, org+team scoped) alongside existing `authorizations`.
8. Frontend: `resources/js/pages/my/overtime-requests/index.tsx` + `create.tsx` mirroring my/leaves. Update `overtime/queue/index.tsx` to add a "Solicitudes" tab showing request rows with approve/reject dialogs (same page, not a new route). Update `overtime/index.tsx` hub to add "Nueva solicitud"/"Mis solicitudes" buttons gated by can.request (permission + mode).
9. Lang: add es/en `ui.overtime.requests.*` and queue tab/columns for requests.
10. Pest tests: same-day request, future request, retroactive inside/outside window (Spanish message), rejection, unanswered request never payable, UI/routes absent under post-hoc mode, org scoping, self-only requesting, queue shows pending requests to supervisor.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Post-review hardening on top of the original implementation: fixed a timezone bug in the retroactive-window check (was using server UTC 'today' instead of TimeZoneService's Chile-calendar day), rejected zero-hour requests, wired up the previously-dead status-filter tabs on Mis solicitudes, switched the overtime-hours input from a split hours/minutes control to a plain decimal field, gave Pendiente/Aprobada/Rechazada their real amber/green/red colors via the shared status-tone palette, and extended the supervisor queue's Solicitudes tab with Pendiente/Aprobada/Rechazada/Todas history tabs (previously hardcoded to pending-only, so a decided request simply vanished from view). Also fixed a related pre-existing bug: the Jornadas tab's own 'Todas' button silently did nothing because the frontend dropped the status param instead of sending the literal 'all'. A follow-up ticket (KOL-66) tracks adding a direct sidebar link to the queue with a pending-count badge.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Employees can request overtime ahead of time (same-day, future, or retroactive within the tenant window) from Mis solicitudes; supervisors decide requests in the same KOL-44 queue via a Solicitudes tab with full Pendiente/Aprobada/Rechazada/Todas history, not a separate inbox. Hidden entirely under pure post-hoc mode. 137 Overtime* Pest tests and the full 1015-test suite pass (2 unrelated pre-existing UpcomingShiftsApiTest flakes aside).
<!-- SECTION:FINAL_SUMMARY:END -->
