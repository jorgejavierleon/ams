---
id: KOL-11
title: Model the overtime authorisation record as the only source of payable hours
status: To Do
assignee: []
created_date: '2026-08-04 11:10'
updated_date: '2026-08-06 10:36'
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
- [ ] #1 An authorisation record exists per employee and day, holding the calculated, requested and authorised hour figures separately rather than collapsed into one number
- [ ] #2 The record carries a status of pending, approved or objected, and records who decided, when, and their reason
- [ ] #3 No elapsed time can move a record to approved; a record nobody acts on stays pending indefinitely and is simply never exported
- [ ] #4 A record can optionally reference the pacto that covers it
- [ ] #5 Given a day with calculated overtime, the system can answer whether those hours are authorised and how many of them are, without recomputing attendance
- [ ] #6 Hours worked beyond the authorised amount remain queryable as unauthorised rather than being dropped or merged into the payable figure
- [ ] #7 The record is organization-scoped and a tenant can never read, approve or object to another tenant record
- [ ] #8 Pest tests cover a fully authorised day, a partially authorised day (worked 3h, authorised 2h), an objected day, a pending day that stays pending, and tenant isolation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
