---
id: KOL-21
title: 'Report: Detalle Semanal por Trabajador'
status: Done
assignee: []
created_date: '2026-08-04 11:14'
updated_date: '2026-08-30 19:10'
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
- [x] #1 The report shows one worker's week day by day with real entrada, salida and colacion against the theoretical shift values
- [x] #2 Time differences for entry and exit are shown per day using the values already computed on the workday
- [x] #3 Each day surfaces its workday status and whether a mark modification is pending or was approved for it
- [x] #4 A day with no marks, a day on leave and a day with an incomplete pair each render sensibly rather than as blanks or errors
- [x] #5 The report exports to Excel and PDF matching the on-screen content
- [x] #6 Whether column selection is included is decided against the simple-builder constraint and the reasoning recorded in the notes
- [x] #7 The export is recorded in the export audit history
- [x] #8 All labels are in Spanish
- [x] #9 Pest tests cover a normal week, a week containing an absence, a leave and an incomplete day, and both export formats
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
1. Backend: WeeklyDetailReportBuilder (single-employee week-by-week grid from Workday's already-computed mark/shift/diff fields + ShiftDay lunch lookup for theoretical colacion + leave + mark-modification pending/approved flags), reusing ReportEmployeeSelector::resolve() and requiring exactly 1 resolved employee.
2. Backend: WeeklyDetailReportExporter (Excel+PDF only, shared ReportWriter/document.blade shell, same fragment renders screen+file).
3. Backend: WeeklyDetailReportController (index + export), reusing ResolvesReportEmployeeFilters, PayrollExportReadinessService for confirm+audit, same pattern as PayrollSummaryReportController. Routes under existing payroll-reports View/Export permission groups.
4. Frontend: weekly-detail.tsx page reusing PeriodSelector/ReportFilterForm/CollaboratorFilter unchanged; renders one table per ISO week with real vs theoretical entrada/salida/colacion, diffs, status badge, leave badge, modification indicators; prompts to select exactly one worker otherwise. Add types to types.ts. Add nav item.
5. Spanish/English translations for all new labels.
6. Pest tests: normal week, week with absence, week with leave, incomplete day, Excel+PDF export content match, audit log record, exactly-one-employee guard, tenant scoping.
7. pint, wayfinder:generate, npm run types:check, sa test --compact.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Backend: WeeklyDetailReportBuilder (individual level — build() only renders once ReportEmployeeSelector::resolve() gives exactly 1 id; reads mark_in_at/mark_out_at/shift_start_time/shift_end_time/in_time_difference/out_time_difference straight off Workday, expands the selected month into whole ISO weeks like DailyReportService, resolves theoretical colacion via ShiftDay(shift_id, weekday) since the system captures no real colacion marks (mirrors the DT daily report's 'No aplica'), surfaces status + pending/approved MarkModification flags per day). WeeklyDetailReportExporter (Excel+PDF only per RF-1, shares document.blade.php shell with the summary exporter). WeeklyDetailReportController mirrors PayrollSummaryReportController (readiness confirm+audit via PayrollExportReadinessService, same View/Export permissions). Routes added under the existing payroll-reports permission groups. Frontend reuses PeriodSelector/ReportFilterForm/CollaboratorFilter unchanged; page shows a 'select exactly one worker' prompt until the shared selection resolves to exactly one employee, then one table per ISO week. Added a second sidebar nav item (Detalle semanal por trabajador) since there are now two RF-1 reports. AC #6: column selection was not built — fixed column set, per the PRD's simple-builder / scope-inflation guidance; Talana's per-report field picker was judged not worth the complexity for the first individual-level report. 18 new Pest tests (normal week, absence, incomplete day, leave, theoretical colacion, pending/approved modification, exactly-one-employee guard on both index and export, both export formats, cross-format content match, audit log, permissions, tenant scoping) all pass. pint, phpstan (composer types:check), tsc --noEmit and eslint all clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Shipped the individual-level 'Detalle Semanal por Trabajador' report (RF-1, Talana's Reporte Semanal Persona equivalent): real vs. theoretical entrada/salida/colación day-by-day, one ISO week per table, for a single worker selected via the shared KOL-19 filter/picker. Reuses Workday's already-computed diff fields, surfaces status and pending/approved mark-modification flags, exports to Excel/PDF via the shared ReportWriter, and records every export in the payroll_export audit log. No column picker was built (AC #6, recorded rationale: fixed columns per the PRD's simple-builder constraint). 18 new Pest tests; full suite (1259 passed/7 skipped), pint, phpstan and tsc/eslint all clean.
<!-- SECTION:FINAL_SUMMARY:END -->
