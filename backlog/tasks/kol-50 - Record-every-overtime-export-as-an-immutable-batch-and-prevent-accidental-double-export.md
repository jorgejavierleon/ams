---
id: KOL-50
title: >-
  Record every overtime export as an immutable batch and prevent accidental
  double export
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
  - KOL-49
  - KOL-17
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 1500
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD sections 7.7 and 8. Generating an export is an event with legal weight — it is the moment a figure becomes a payment obligation — so it produces a permanent record of what was sent and when, and the same period cannot be exported twice by accident.

The batch stores an **immutable snapshot** of the lines as they were at generation time, not a query that re-runs later. This is the difference between an export that can be reconciled against a payroll run six months later and one that quietly reports different numbers once a workday is recalculated. Zero incidents of duplicate export for the same period is one of the PRD success metrics.

What lands:
- A batch record: period, generation timestamp, generating user, line count.
- Immutable lines snapshotting exactly what was exported.
- A guard on re-exporting a period already exported, which warns and requires explicit confirmation rather than refusing outright — a client re-running a period after a correction is a legitimate case, and both runs need to be on the record.
- Output as structured CSV and Excel per PRD section 7.7.

**Check KOL-17 before starting.** That task builds the general payroll export audit history, and this is the same concern for the overtime slice. If KOL-17 has landed, extend it rather than building a parallel batch table; if it has not, coordinate the shape so the two do not diverge. Likewise the multi-format writing belongs to KOL-15, and `app/Services/Reports/DtReportExporter.php` already writes Excel, PDF and Word for the Resolución 38 reports.

The optional API or webhook for direct payroll integrations named in the PRD is out of scope here — KOL-27 and KOL-28 already cover scoping that.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Generating an export produces a batch record carrying the period, the generation timestamp, the generating user and the line count
- [ ] #2 The exported lines are snapshotted immutably, so a later recalculation of a workday never changes what a past export says
- [ ] #3 Re-exporting a period that was already exported warns the user and proceeds only on explicit confirmation, and both runs remain on the record
- [ ] #4 Exports are produced as structured CSV and Excel
- [ ] #5 The batch reuses the general payroll export audit history from KOL-17 rather than introducing a parallel one, or the divergence is justified in the notes
- [ ] #6 The export history is viewable in Spanish and is organization-scoped
- [ ] #7 Pest tests cover a first export, a re-export of the same period, a snapshot surviving a workday recalculation unchanged, and tenant isolation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
