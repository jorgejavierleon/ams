---
id: KOL-51
title: >-
  Make every exported overtime figure walkable back to its raw mark and its
  approver
status: To Do
assignee: []
created_date: '2026-08-06 02:55'
labels:
  - overtime
  - backend
  - frontend
  - compliance
milestone: m-2
dependencies:
  - KOL-46
  - KOL-50
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 1600
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 7.8 and the auditability requirement in section 9. The module promise is that any exported figure can be walked back to its origin: **raw mark → calculated overtime → request if any → authorisation → final exported value**. This task makes that walk possible as a single answerable question rather than something an engineer reconstructs by hand from four tables.

Requirements that shape it:
- **Every state change is recorded in an append-only log.** Nothing is updated in place, nothing is deleted. Spatie activitylog is already installed and used this way in `app/Actions/Documents/` — follow that rather than adding a mechanism.
- **Raw marks stay immutable.** No operation in this module may edit or delete an original `Mark`; corrections are already modelled as linked `MarkModification` records, which is the pattern to preserve.
- **Manual corrections are visible as such.** Where a figure derives from a corrected mark rather than an original punch, the trail says so — Talana carries a manual-correction column in its reports for exactly this reason, and an auditor asking why a figure differs from the raw punch deserves that answer on the report rather than on request.
- An exportable audit report aligned with the four mandatory Resolución 38 DT reports, following the shape of the existing ones in `app/Services/Reports/` and their exporter.

The test that matters here is the end-to-end one: take an exported line, walk it back to the punch that produced it, and assert every intermediate decision and its author are present.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Given an exported line, the full chain from raw mark through calculation, request, authorisation and final figure is retrievable, with the acting user and timestamp at each human step
- [ ] #2 Every state change on an overtime record is written to an append-only log using the activity log already in the project, with no in-place overwrite of history
- [ ] #3 No operation in the module can edit or delete an original attendance mark; corrections remain modelled as linked records
- [ ] #4 A figure derived from a corrected mark rather than an original punch is identifiable as such in the trail and on the report
- [ ] #5 An audit report exports in the formats used by the existing Resolución 38 reports, in Spanish
- [ ] #6 The trail is organization-scoped
- [ ] #7 Pest tests include an end-to-end walk from an exported line back to the originating punch asserting every intermediate decision and author is present, plus a case involving a corrected mark
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
