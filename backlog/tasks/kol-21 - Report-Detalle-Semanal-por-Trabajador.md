---
id: KOL-21
title: 'Report: Detalle Semanal por Trabajador'
status: To Do
assignee: []
created_date: '2026-08-04 11:14'
labels:
  - payroll-reports
  - backend
  - frontend
  - report
milestone: m-0
dependencies:
  - KOL-15
  - KOL-19
documentation:
  - docs/prd-reports.md
priority: medium
type: feature
ordinal: 20000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-1's equivalent of Talana's 'Reporte Semanal Persona' — the report RRHH opens when a number in the resumen looks wrong and they need to see the week that produced it. Individual level, one worker at a time.

The defining feature per PRD section 2.1 is **real versus theoretical side by side**: actual entrada, salida and colación against what the assigned shift said they should have been, so a discrepancy is visible without arithmetic. `workdays` already stores both halves of this — `mark_in_at`/`mark_out_at` alongside `shift_start_time`/`shift_end_time`, with `in_time_difference` and `out_time_difference` as the deltas already computed. `app/Services/WorkdayPresenter.php` and `app/Services/Reports/DailyReportService.php` are worth reading first; the DT daily report solves a closely related presentation problem.

Anomalies must be visible on the row: the workday status (irregular, incomplete, absent) and whether a mark modification is pending or was approved for that day. This is the screen KOL-14's warnings should be able to link into.

Talana lets the user choose which of these fields to display. Treat column selection as desirable but secondary — the PRD's 'builder simple' constraint and the scope-inflation risk in section 10 both argue against building a general field picker here. If it is cheap on top of the shared filter, include it; otherwise ship a fixed sensible column set and note the decision.

Formats: Excel and PDF per RF-1.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The report shows one worker's week day by day with real entrada, salida and colacion against the theoretical shift values
- [ ] #2 Time differences for entry and exit are shown per day using the values already computed on the workday
- [ ] #3 Each day surfaces its workday status and whether a mark modification is pending or was approved for it
- [ ] #4 A day with no marks, a day on leave and a day with an incomplete pair each render sensibly rather than as blanks or errors
- [ ] #5 The report exports to Excel and PDF matching the on-screen content
- [ ] #6 Whether column selection is included is decided against the simple-builder constraint and the reasoning recorded in the notes
- [ ] #7 The export is recorded in the export audit history
- [ ] #8 All labels are in Spanish
- [ ] #9 Pest tests cover a normal week, a week containing an absence, a leave and an incomplete day, and both export formats
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
