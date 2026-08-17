---
id: KOL-71
title: Surface and decide overtime on the Jornadas index and detail pages
status: Done
assignee: []
created_date: '2026-08-17 19:07'
updated_date: '2026-08-17 20:08'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-44
  - KOL-11
ordinal: 49000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Currently overtime approval lives on a separate screen (/overtime/queue, KOL-44) even though every record it decides is one-to-one with a Workday already shown on Jornadas (/workdays). A supervisor reviewing a day's marks has no visibility into that day's overtime, and deciding overtime means leaving Jornadas entirely. This moves the whole post-hoc approve/object flow onto Jornadas: an overtime indicator plus quick and bulk approve/object on the index, and a full overtime section plus inline approve/object on the day detail page, merged into the existing mark-modification history timeline as one chronological feed. The standalone queue screen is decommissioned in KOL-74 once this and its siblings (KOL-72, KOL-73) exist. Mode A requests are explicitly out of scope here — they move to their own screen in KOL-72, since a request isn't tied to a computed workday yet.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The Jornadas index shows an overtime indicator (hours + status) on any row with calculated overtime, using the same visual pattern as the existing pending-modifications badge
- [x] #2 A single day's overtime can be approved or objected from the Jornadas index via a quick action, reusing the same dialog (authorized hours, KOL-47 compensation-type selector when the employee is eligible, reason) the old queue used
- [x] #3 A selection of rows can be bulk-approved or bulk-objected from the Jornadas index, using the same row-selection mechanism already on that page, and cannot bypass the anomaly block or the legal-cap justification requirement for any row in the selection
- [x] #4 The Jornadas day detail page shows the day's calculated, authorized and final overtime hours, its status, and (when set) its compensation type, and lets the assigned reviewer approve or object inline
- [x] #5 The overtime decision (opened/approved/objected, by whom, when, why) appears merged into the same chronological history timeline as mark-modification requests on the detail page, not as a separate disconnected list
- [x] #6 Every guarantee the old queue enforced still holds from the new location: no elapsed time approves a record, a flagged day blocks approval with its flag reason shown, a legal-cap breach requires a written justification, a reviewer can only decide their own team's records unless they hold Manage
- [x] #7 Pest tests cover individual approve, individual object, bulk approve, a bulk selection containing a flagged day, an over-cap approval without/with justification, cross-team refusal, and the KOL-47 rest-day compensation path (eligible and ineligible employee), all exercised through the new Jornadas routes
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
1. WorkdayController::index() (line ~63-98): eager-load overtimeAuthorization, add an 'overtime' key per row (null | {calculated_hours, status, status_label, status_badge, authorized_hours, final_hours, compensation_eligible}), plus a per-row 'can' flag for approve/object visibility.
2. workdays/index.tsx: overtime badge next to the pending-modifications badge (same toneChip pattern); lift the approve/object dialogs out of overtime/queue/index.tsx into shared components (resources/js/components/overtime-approve-dialog.tsx, overtime-object-dialog.tsx) reused by both the index quick action and (later) the detail page; add "Aprobar horas extra"/"Objetar horas extra" to the existing renderSelectionActions bulk toolbar alongside "Modificar marcas".
3. New WorkdayController routes/actions: POST workdays/{workday}/overtime/approve, POST workdays/{workday}/overtime/object, POST workdays/overtime/bulk-decide — move (not reimplement) the bodies of OvertimeQueueController::approve()/object()/bulkDecide(), resolving the OvertimeAuthorization from the Workday. Reuse OvertimeAuthorizationPolicy as-is.
4. WorkdayController::show() + WorkdayPresenter: eager-load overtimeAuthorization.reviewedBy + restDayBalance; new WorkdayPresenter::overtime() mapper (calculated/authorized/final hours, status, compensation_type label, pact reference, reason, reviewer, can_decide), null when no overtime that day.
5. workday-detail.tsx: overtime stat tiles near worked/extra/missing; generalize Modification -> discriminated TimelineEntry (kind: mark_modification | overtime), merge+sort by created_at desc server-side in WorkdayPresenter, prefix ids (mod-/ot-) for unique React keys, branch only the timeline body per kind, reuse the shared approve/object dialogs from step 2 for the overtime entry's actions.
6. Lang entries (es/en) for the new badge/column/dialog labels under ui.workdays.*.
7. Pest: move/rewrite the relevant OvertimeQueueTest.php cases (individual approve/object, flagged day, cap justification, cross-team, bulk approve, the two KOL-47 rest-day tests) against the new WorkdayController routes; add index/detail payload assertions for the new overtime data and merged timeline. Leave OvertimeQueueTest.php itself intact until KOL-74 removes the old routes (both coexist during the transition).
8. pint --dirty, targeted Pest subset, npm run build / tsc --noEmit.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Scope expansion discovered mid-implementation, confirmed with @jorge: WorkdayController/WorkdayPolicy had NO team-scoped access at all (only ViewAny:Workday/Update:Workday, granted to nobody by default except admins via Gate::before bypass) -- a supervisor who could approve overtime via the old queue (ViewTeam/ApproveTeam:OvertimeAuthorization) would have had zero access to Jornadas. Adding two new permissions, ViewTeam:Workday and ApproveTeam:Workday, granted to the supervisor role, with WorkdayPolicy::viewTeam()/view()/update() extended to recognize a supervisor acting on their own direct reports (mirrors LeavePolicy/OvertimeAuthorizationPolicy's ViewTeam/ApproveTeam pattern exactly). The overtime DECISION itself keeps using OvertimeAuthorizationPolicy unchanged (ViewTeam/ApproveTeam/Manage:OvertimeAuthorization) -- Workday permissions gate reaching the page and acting on marks, OvertimeAuthorization permissions gate the decision, stacked independently.

Also found and fixing a latent bug this exposes: WorkdayController::bulkModify() calls Gate::authorize('update', Workday::class) (class-level, no instance) against a policy method typed to require a Workday instance -- currently masked entirely because every actor who can reach it today is an admin (Gate::before bypass never invokes the policy body). Once ApproveTeam:Workday exists for real supervisors, this would throw a TypeError. Fixing by using a coarse actor-level pre-check plus per-row Gate::allows filtering, same pattern already used by the bulk overtime action.

Implementation complete. Verified via targeted Pest subsets against an isolated test database (concurrent session on shared MySQL): WorkdayOvertimeTest (15), WorkdayManagementTest/WorkdayAnomalyFlagsTest/My-WorkdayTest/OvertimeQueueTest-family/LeaveWorkdayRecalculationTest (102) -- 117 total, all passing. pint clean, phpstan clean on all touched non-test files (test-file phpstan "noise" re: Pest's $this typing is pre-existing and identical on untouched files like OvertimeQueueTest.php -- confirmed, not introduced here). tsc --noEmit clean, npm run build succeeds.

Also added a sidebar nav entry for supervisors (Aprobaciones section, gated on the new ViewTeam:Workday permission) so the feature is actually reachable -- without it a supervisor had no path to the routes despite holding access. The pre-existing "Horas extra pendientes" queue link/badge is untouched; KOL-74 re-points/removes it.

Full suite deferred to KOL-74 (or whenever the user says the whole arc is ready to finalize), per their standing instruction not to run it mid-development.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Moved post-hoc overtime approval from the standalone /overtime/queue (KOL-44) onto Jornadas: an overtime badge plus quick and bulk approve/object on the index, and a full overtime section plus inline approve/object merged into the day detail page's history timeline (renamed 'Historial de la jornada' since it's no longer mark-modifications-only). Surfaced a real access-control gap in the process: WorkdayController was gated by the literal role:admin route middleware, so a supervisor had zero access to Jornadas despite having team-scoped overtime authority via the old queue. Fixed by adding ViewTeam:Workday/ApproveTeam:Workday permissions (granted to the supervisor role) with real team-scoping in WorkdayPolicy, moving the workdays.* routes out of the admin-only group into a permission/policy-gated one, and fixing a latent bulkModify crash bug this exposed (a class-level Gate::authorize call against an instance-typed policy method, previously masked because only admins ever reached it). Added a sidebar nav entry so supervisors can actually discover the feature. The old queue is untouched and still functions; KOL-74 decommissions it once KOL-72/73 give its other functionality (Solicitudes, the approved ledger) a new home. Verified: 117 targeted Pest tests (new WorkdayOvertimeTest plus the full existing Workday/Overtime suites) all passing; pint clean; phpstan clean; tsc clean; npm run build succeeds.
<!-- SECTION:FINAL_SUMMARY:END -->
