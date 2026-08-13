---
id: KOL-42
title: >-
  Manage pactos de horas extraordinarias with their three-month statutory
  validity
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-06 02:52'
updated_date: '2026-08-13 09:17'
labels:
  - overtime
  - backend
  - frontend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-11
  - KOL-37
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 800
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 7.6. Código del Trabajo art. 32 requires overtime to be agreed in writing, for a maximum of three months, renewable, and only for transitory needs of the business. A tenant that has switched on the pacto requirement in KOL-37 cannot approve overtime without a valid one behind it.

CRUD for the agreement itself: employee, date range, status. The three-month ceiling is a validation on the range, and renewal creates a new agreement rather than extending the old one — extending in place would destroy the evidence of what was agreed when, which is the only reason the record exists.

Behaviour that matters at the edges:
- An agreement about to expire raises an alert, so overtime does not silently become unapprovable mid-period.
- An overtime record links to a valid agreement when one exists. When the tenant requires one and none covers the date, the record stays pending with that stated as the explicit reason — not a generic pending, but *pending because there is no valid pacto*, so the person looking at the queue knows what to do about it.
- Validity is judged against the date the overtime was worked, not the date it is being approved. Approving in early September an hour worked in late August must consult the agreement that was in force in late August.

UI in Spanish, managed by users holding the right permission from KOL-43. The list follows the shared DataTable foundation used by every other list screen in the app.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 An overtime agreement can be created for an employee over a date range, and a range longer than three months is refused with a Spanish message citing the reason
- [x] #2 Renewal produces a new agreement rather than extending an existing one, so the history of what was agreed and when is preserved
- [x] #3 An agreement nearing expiry raises an alert to the users responsible for it
- [x] #4 An overtime record links to the agreement covering its worked date, judged by the date worked and not the date approved
- [x] #5 Agreements are listed, created, edited and revoked from the UI in Spanish by a user holding the right permission
- [x] #6 Agreements are organization-scoped and never visible across tenants
- [x] #7 When no agreement covers the worked date, the record is flagged with that specific reason and can still be approved with a mandatory written justification; the absence of an agreement never blocks payment
- [x] #8 Pest tests cover a valid agreement, one exceeding three months, a renewal, an expired agreement at approval time, a missing agreement approved with a written justification and refused without one, and tenant isolation
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
1. Migration: create `overtime_pacts` (organization_id, user_id, start_date, end_date, status, expiry_notified_at nullable, timestamps); second migration adds the FK on overtime_authorizations.overtime_pact_id (stays nullable).
2. Enum OvertimePactStatus {Active, Revoked} — no stored Expired state; expiry is derived from end_date vs today, matching this app's existing "no lapsed status" pattern (OvertimeAuthorizationStatus).
3. Model OvertimePact (BelongsToOrganization): scope active(), scope coveringDate(), static coveringDateFor($userId, $date, $orgId), revoke(), nearingExpiry($withinDays).
4. Model OvertimeAuthorization: approve() resolves the pact covering `date` (worked date, not today) at approval time and stores it on overtime_pact_id; booted() gains a check mirroring the existing legal-cap breach check — approving with no covering pact requires a non-blank `reason`, exactly like exceeding a legal cap does. New OvertimeDecisionRefused::withoutPactCoverage().
5. OvertimePactController (index/store/update/revoke — no hard delete, only revoke), gated by the existing Manage:OvertimeAuthorization permission (no new permission), following the CostCenterController convention (no separate Policy class).
6. Routes nested under the existing `overtime` group in routes/web.php.
7. Frontend: resources/js/pages/overtime/pacts/index.tsx (DataTable) + overtime-pact-form-dialog.tsx (create/edit), following the cost-centers dialog pattern; a small link added to overtime/index.tsx so the screen is reachable.
8. Near-expiry alert: new console command overtime:pacts:notify-expiring (scheduled daily), delegating to a small service that mails every user holding Manage:OvertimeAuthorization in the pact's org, once per pact (expiry_notified_at idempotency column), mirroring the Notification pattern used for leaves.
9. Pest feature tests covering AC #1-8 (3-month cap + Spanish message, renewal as a new row, expiry notification idempotency, pact resolved by worked date not approval date, full CRUD + revoke through the permission gate, tenant isolation, missing-pact approval requires justification and is never blocked).
10. vendor/bin/pint --dirty, sa test --compact, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified: vendor/bin/pint --dirty clean; sa test --compact --filter=Overtime passes 96/96 (361 assertions); npm run types:check clean. Full suite run separately shows 20 pre-existing failures, all unrelated to overtime (storage/framework/testing/disks/public permission-denied errors in Document signing/avatar upload tests), confirmed none touch Overtime/Pact code.
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @jorge
created: 2026-08-08 15:27
---
Amended, and the KOL-37 tenant switch it referenced is gone (removed in KOL-57).

Art. 32 requires overtime to be agreed in writing, but the absence of that agreement does not make the hours cease to be overtime: the DT reality criterion — stated by the PRD itself at line 22 — holds that hours worked with the employer's knowledge are payable whether or not a written pacto exists. A rule making a record unapprovable without one therefore produces an unlawful outcome, not a conservative one.

The correct shape is already in the source: Res. 38 art. 45.2 says of the excessive-shift alert that it 'no impedirá la carga de la jornada, sino que sólo constituirá un aviso para el empleador'. KOL-41 implements exactly that for legal caps. A missing pacto is a flag demanding a written justification, never a bar. See decision-1.

Everything else in this task stands: the three-month ceiling, renewal creating a new record rather than extending, validity judged by the date worked, and the expiry alert. Caveat carried from decision-1: the Código del Trabajo text is not in the repo, so the art. 32 reading here must be confirmed with the labor advisor before this task is finalised.
---

created: 2026-08-11 00:09
---
The pacto has no effect on whether overtime gets approved — both a valid pacto and a missing one (flagged, per decision-1) end at the same supervisor decision, requiring a written justification only in the missing/exceeded case. The actual payoff is in KOL-47: per its AC #8, compensation type (payment vs. rest days) is resolved from the worker's written agreement in force on the day, and with no valid agreement the hours are payment-compensated with that fallback not configurable by anyone. So a pacto's functional benefit is unlocking rest-day compensation, not gating approval. Make sure this dependency stays explicit when KOL-47 is picked up.
---

created: 2026-08-12 01:09
---
Reviewed against docs/context/horas_extras_codigo_trabajo.txt (Art. 30-33 of the Código del Trabajo, now in the repo). This resolves the caveat from decision-1 and comment #1: no amendment needed, the task's reading was correct.

Art. 32 confirms verbatim what the task already assumes:
- "Dichos pactos deberán constar por escrito y tener una vigencia transitoria no superior a tres meses, pudiendo renovarse por acuerdo de las partes" — written, max 3 months, renewable. Matches AC #1/#2.
- "No obstante la falta de pacto escrito, se considerarán extraordinarias las que se trabajen en exceso de la jornada pactada, con conocimiento del empleador" — the DT reality criterion cited in comment #1 is the statute's own text, not an inference. Confirms AC #7's flag-plus-justification shape is legally correct, and that treating a missing pacto as a bar would be the unlawful outcome.

Two adjacent rules in this same text are relevant to the module but not to this task:
- Art. 31's 2-hour/day ceiling on pactadas overtime is a distinct cap from the 3-month pacto duration; it belongs with the legal-limits work (KOL-36/KOL-41), not this CRUD.
- Art. 32's rest-day compensation mechanics (written agreement, 5-day/year cap, 6-month use window, 48h notice, 1 hr extra = 1.5 hr rest, art. 73 settlement at termination) are KOL-47's concern per comment #2, not this task's.
---

created: 2026-08-13 09:03
---
Added a reactivate path: a revoked pacto can be flipped back to Active from the same row (OvertimePact::activate(), PATCH overtime/pacts/{id}/activate, no confirmation dialog since it's non-destructive). Requested after review — revoking had no way back short of editing the DB. Covered by two new tests in OvertimePactManagementTest (reactivate + tenant isolation on activate).
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added OvertimePact CRUD (create/edit/revoke/reactivate) with a three-month range cap, renewal-as-new-record, worked-date pact resolution at approval time, near-expiry email notification (daily console command), and the DataTable-based Spanish UI, gated by the existing Manage:OvertimeAuthorization permission. A missing pacto never blocks approval — it requires a written justification, mirroring the legal-cap breach path (Art. 32 DT reality criterion). Verified via Pest (OvertimePactManagementTest, OvertimePactCoverageTest, OvertimePactExpiryNotificationTest, plus updated OvertimeAuthorizationTest/OvertimeCapValidationTest), pint, and tsc.
<!-- SECTION:FINAL_SUMMARY:END -->
