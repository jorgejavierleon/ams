---
id: KOL-11
title: Model the overtime authorisation record as the only source of payable hours
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-04 11:10'
updated_date: '2026-08-09 14:57'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-39
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: task
ordinal: 450
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
**Rescoped.** This task originally carried the whole overtime problem — the calculation, the caps, the anomalies, the UI and the export — and was too large to execute. That scope now lives in milestone m-2 as a sequence of tasks. What remains here is the piece the rest converges on: the record that decides how many hours are payable.

PRD section 8, `OvertimeAuthorization`. This is the single source of truth for what will be exported, and it is the only place a payable figure can be born. The calculation engine cannot write one (KOL-39); the export can read nothing else (KOL-49).

What the record holds:
- The day it authorises, for one employee, organization-scoped.
- The three hour figures from the glossary, kept separately rather than collapsed: **OHC** calculated by the engine, **OHR** requested by the employee if the tenant runs Mode A, **OHA** authorised by the approver. Keeping all three is what makes the final figure explainable — an accountant asking why 2 hours and not 3 gets an answer.
- The resulting final figure, per KOL-46.
- Status: `pending`, `approved` or `objected`. Never auto-approved by timeout — PRD section 7.5 is explicit that an ungoverned record simply is not exported, it is not assumed approved by default.
- Who approved or objected, when, the optional reason (mandatory when over a cap, per KOL-41), and an optional link to the pacto that covers it (KOL-42).

The state machine follows the shape already in the codebase rather than a new pattern: `app/Enums/MarkModificationStatus.php` for the pending/approved/declined enum with its `label()`, `badge()` and `options()` methods, and `app/Models/MarkModification.php` for the reviewer/reviewed-at columns. Organization scoping comes from `app/Models/Concerns/BelongsToOrganization.php`, as everywhere else.

Hours worked beyond what was authorised stay visible as unauthorised rather than being dropped or silently paid — that difference is what KOL-13 and KOL-24 will report on.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 An authorisation record exists per employee and day, holding the calculated, requested and authorised hour figures separately rather than collapsed into one number
- [x] #2 The record carries a status of pending, approved or objected, and records who decided, when, and their reason
- [x] #3 No elapsed time can move a record to approved; a record nobody acts on stays pending indefinitely and is simply never exported
- [x] #4 A record can optionally reference the pacto that covers it
- [x] #5 Given a day with calculated overtime, the system can answer whether those hours are authorised and how many of them are, without recomputing attendance
- [x] #6 Hours worked beyond the authorised amount remain queryable as unauthorised rather than being dropped or merged into the payable figure
- [x] #7 The record is organization-scoped and a tenant can never read, approve or object to another tenant record
- [x] #8 Pest tests cover a fully authorised day, a partially authorised day (worked 3h, authorised 2h), an objected day, a pending day that stays pending, and tenant isolation
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
1. Migration create_overtime_authorizations_table: workday_id (unique FK), user_id, date, calculated/requested/authorized/final hours kept separately as time columns, status, reviewed_by/reviewed_at/reason (MarkModification shape), nullable overtime_pact_id (FK lands with KOL-42), org scoping + indexes.
2. Enum OvertimeAuthorizationStatus (pending|approved|objected) with label()/badge()/options(), es+en lang entries under ui.overtime.authorization_statuses.
3. App\\Support\\Duration value object for HH:MM:SS arithmetic to the second (min, minus, zero), reused by KOL-46.
4. Model OvertimeAuthorization: BelongsToOrganization, relations, openFor(Workday) opening a pending snapshot, approve(User,hours,reason)/object(User,reason) as the only writers of a terminal status, a saving guard refusing any non-pending row without a reviewer and timestamp, cross-tenant reviewer refused, scopes approved/pending/objected, authorizedOvertime()/unauthorizedOvertime().
5. Workday: overtimeAuthorization() hasOne + thin authorizedOvertime()/unauthorizedOvertime() accessors answering from the record without recomputing attendance.
6. OvertimeAuthorizationFactory with approved/objected/partial states.
7. Pest OvertimeAuthorizationTest: fully authorised day, partial (3h worked / 2h authorised), objected day, pending day surviving a year and the approve-overdue scheduler, tenant isolation on read and on approve.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as a new overtime_authorizations table plus App\Models\OvertimeAuthorization. Design points worth carrying forward:

- OvertimeAuthorizationStatus has exactly pending|approved|objected. The absent 'lapsed' case is the AC#3 guarantee: PRD 7.5 forbids auto-approval by timeout, so there is no value the enum could hold to express it. Deliberately the opposite reading of MarkModificationStatus, where silence does consolidate after the art. 40 d window.
- The timeout guarantee is enforced on the model's saving hook, not at call sites: any row in a status where requiresReviewer() is true and reviewed_by/reviewed_at are absent throws OvertimeDecisionRefused::withoutAReviewer(). A cron, backfill or queue job hits it exactly as a controller would. approve()/object() both take a User as their first argument, so no signature omits the human.
- Cross-tenant writes are refused too (OvertimeDecisionRefused::byAnotherTenant()), covering the write the way OrganizationScope covers the read.
- Four hour columns kept apart (calculated/requested/authorized/final). Null means 'this tenant has no such figure', never zero: Duration::min() skips absent inputs rather than flooring to nothing.
- final_hours = MIN(authorized, calculated) only. Requested is recorded but outside the comparison, per KOL-46 AC#2. KOL-46 still owns the legal-cap ceiling and the post-approval-recalculation rule.
- New App\Support\Duration value object for HH:MM:SS arithmetic to the second (string comparison would get '10:00:00' vs '9:00:00' wrong). KOL-41/46 should reuse it.
- overtime_pact_id is deliberately an unconstrained nullable column; KOL-42 creates the pactos table and adds the FK.
- OvertimeAuthorization::openFor(Workday) opens a pending record with a snapshot of the OHC. Deliberately NOT wired into CalculateOvertime, so KOL-39's guarantee that the engine neither writes nor knows about this record stands. KOL-44 (pending-overtime queue) is the natural caller.
- Backend only, no UI, so nothing was added to docs/QA_CHECKLIST.md. docs/architecture.md gained an 'Overtime authorisation (OHA)' section.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added the overtime_authorizations table and App\Models\OvertimeAuthorization as the single source of payable hours: OHC, OHR, OHA and the derived final figure kept as separate columns, a pending|approved|objected status with no lapsed case, and a model saving guard that refuses any decided row without the person who decided it — so no cron, backfill or future job can age a record into being payable. Unauthorised hours stay queryable rather than dropped, reviewers from another tenant are refused on the write path, and hour arithmetic runs to the second through the new App\Support\Duration. 16 Pest tests; docs/architecture.md gained an 'Overtime authorisation (OHA)' section.
<!-- SECTION:FINAL_SUMMARY:END -->
