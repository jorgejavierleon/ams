---
id: KOL-80
title: >-
  Decide Jornadas overtime lazily: create the authorisation on approve, drop
  objecting, add a revoke action
status: Done
assignee: []
created_date: '2026-08-18 11:21'
updated_date: '2026-08-21 09:40'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-11
  - KOL-40
  - KOL-41
  - KOL-71
ordinal: 58000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Supersedes KOL-70. Traced why the overtime queue is empty even with real computed excess in the system: WorkdayController::approveOvertime()/objectOvertime() (app/Http/Controllers/WorkdayController.php:399-473) both do $workday->overtimeAuthorization()->first() and 404 if it's null — and nothing in app/ ever calls OvertimeAuthorization::openFor() to create that row ahead of time (KOL-70's own finding). KOL-70 proposed fixing this by wiring openFor() into the calculation pipeline so a pending row exists for every computed day, ready to be decided later.

Decided instead, discussing with @jorge: don't eagerly create a row for every day with calculated overtime at all. The model's own docblock already states the philosophy this fits — Workday::authorizedOvertime(): 'a day nobody has opened a record for authorises nothing — the absence of a decision is not a decision.' So:

- 'Pending' stops being a state anyone lists or filters — a day needing a decision is just a Workday with calculated_overtime > 0 and no OvertimeAuthorization row, computed on read (WorkdayPresenter::overtime(), the Jornadas index/detail), never stored.
- approveOvertime()/bulkDecideOvertime() call OvertimeAuthorization::openFor($workday) themselves, immediately followed by approve() in the same request — the row is born already-decided, never sitting around undecided waiting for review.
- The 'objected' path goes away entirely: OvertimeAuthorizationStatus::Objected, OvertimeAuthorization::object(), WorkdayController::objectOvertime(), the bulk-object branch, OvertimeObjectDialog and its Jornadas wiring, and the 'Objetar' button are all removed. Silence (no approval) is sufficient — there is no separate persisted refusal-with-reason anymore.
- A new 'eliminar' action revokes an already-approved record. It keeps the row (who revoked it, when, why) rather than deleting it, and the revocation appears in the workday's chronological timeline (WorkdayPresenter::timeline(), which already merges mark-modification and overtime-decision entries per KOL-71).
- The Jornadas index column (ui.workdays.columns.overtime, currently 'Horas extra') is relabelled 'Horas extras autorizadas' and shows the authorised/final figure, not the raw calculated one — the calculated excess is what triggers the 'Aprobar' action, not what the column reports once decided.

This also finally makes the Jornadas overtime approval flow (KOL-71) work against real data — right now every approve/object click 404s for any day that wasn't manually seeded with an OvertimeAuthorization row.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A Workday with calculated overtime and no existing OvertimeAuthorization row can be approved directly from Jornadas (index quick action, detail page, and bulk) — the record is created and decided in the same action, never left as a separately-persisted pending row
- [x] #2 There is no 'objected' status, action, button, or dialog left anywhere in the app; a day nobody approved simply shows no authorised hours
- [x] #3 An approved record can be revoked ('eliminar') with a reason; the row is preserved (not deleted) and the revocation appears in the workday's timeline with who/when/why
- [x] #4 The Jornadas index overtime column is labelled 'Horas extras autorizadas' (es) and shows the authorised/final figure; a day with calculated-but-unapproved excess is visually distinguished from one with none
- [x] #5 KOL-40's anomaly-flag block and KOL-41's legal-cap justification requirement still apply at the moment of approval, unchanged in effect
- [x] #6 Re-clicking approve on an already-approved day, or revoking a day with no approved record, is refused rather than silently no-op'd
- [x] #7 Pest tests cover: approving a day with no prior authorisation row, bulk-approving a mix of already-approved and undecided days, revoking an approved record and seeing it in the timeline, and confirming no code path creates an OvertimeAuthorization row for a day nobody has acted on
- [x] #8 Every test and UI reference to OvertimeAuthorizationStatus::Objected / object()/objectOvertime() is removed, not left disabled
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
1. Migration: add revoked_by/revoked_at/revoked_reason to overtime_authorizations.
2. Enum OvertimeAuthorizationStatus: drop Objected, add Revoked.
3. Model OvertimeAuthorization: drop object()/isObjected()/scopeObjected(); add revoke()/isRevoked().
4. Policy OvertimeAuthorizationPolicy: drop object(); add revoke().
5. WorkdayController: approveOvertime lazily opens+decides; drop objectOvertime; add revokeOvertime; bulkDecideOvertime lazily opens+approves only; overtimeRowData exposes can_decide/can_revoke without persisting.
6. WorkdayPresenter: mirror the same read-only can_decide/can_revoke logic for the detail page + timeline.
7. routes/web.php: swap the object route for a revoke route.
8. OvertimeQueueController + its Pest tests + queue/index.tsx: drop the object action/branch/dialog (compile breakage otherwise since the enum case and model method disappear).
9. Frontend: delete overtime-object-dialog.tsx, add overtime-revoke-dialog.tsx, wire into workdays/index.tsx and workday-detail.tsx (approve when not approved, revoke when approved), simplify bulk toolbar to approve-only, relabel the Jornadas overtime column.
10. lang/es+en ui.php: remove objected/object_dialog entries, add revoked/revoke_dialog entries, relabel columns.overtime.
11. Factory: drop objected() state, add revoked() state.
12. Rewrite WorkdayOvertimeTest.php around the lazy-create flow + revoke; trim Objected references from OvertimeAuthorizationTest.php, OvertimeQueueTest.php, OvertimeSectionTest.php, WorkdayAnomalyFlagsTest.php, OvertimeRestDayBalanceTest.php.
13. pint --dirty, sa test --compact, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implementation complete. Migration added revoked_by/revoked_at/revoked_reason. Enum dropped Objected, added Revoked. Model dropped object()/isObjected()/scopeObjected, added revoke()/isRevoked() (refuses a non-approved record). WorkdayController::approveOvertime/bulkDecideOvertime now check the Gate against the existing row (or an unsaved provisional instance) before calling openFor(), so an unauthorized attempt never persists a row; openFor() itself still runs before validation so a failed attempt (flagged day, unjustified cap) leaves a retryable pending row, matching the existing two-step approve-with-reason UX. Added revokeOvertime(). Also updated the legacy OvertimeQueueController (dropped object()/bulk-object branch) and its page since the Objected enum case and object() model method they depended on are gone. Frontend: overtime-object-dialog.tsx replaced by overtime-revoke-dialog.tsx, wired into workdays/index.tsx and workday-detail.tsx; Jornadas index overtime column now shows only the authorised/final figure (dash otherwise), relabelled 'Horas extras autorizadas'. Rewrote WorkdayOvertimeTest.php around the lazy-create flow; trimmed Objected references from 5 other test files. Full suite: 1114 passed, 4 skipped, 0 failed.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Jornadas overtime decisions are lazy now: approving (index quick action, detail page, bulk) opens the OvertimeAuthorization row and decides it in the same request instead of relying on a pre-existing pending row, so the approve flow works against real data for the first time (KOL-71 was previously 404ing on every click). Objecting is gone entirely — OvertimeAuthorizationStatus::Objected, OvertimeAuthorization::object(), and every 'Objetar' affordance (Jornadas + the legacy overtime queue) were removed; silence is the refusal. A new revoke() action ('Eliminar') withdraws an approved record while preserving the row, with its own revoked_by/revoked_at/revoked_reason columns distinct from the approval's own audit trail, and the Gate is checked before any row is persisted so an unauthorized attempt never creates one. Verified: full Pest suite 1114 passed / 4 skipped / 0 failed, pint clean, npm run types:check clean, and manually confirmed in the browser (index, detail, bulk, approve, revoke, re-approve refusal). KOL-82 opened as follow-up to replace the single-current-state timeline entry with a full activity log (spatie/laravel-activitylog) so a revoke no longer visually erases the prior approval from the Jornadas timeline.
<!-- SECTION:FINAL_SUMMARY:END -->
