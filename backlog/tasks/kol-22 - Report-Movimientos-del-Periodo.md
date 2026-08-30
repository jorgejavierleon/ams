---
id: KOL-22
title: 'Report: Movimientos del Periodo'
status: Done
assignee: []
created_date: '2026-08-04 11:14'
updated_date: '2026-08-30 19:45'
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
ordinal: 21000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-1, modelled on Talana's 'Movimientos del Mes'. Payroll is not only hours — an accountant also needs to know who joined, who left, who started or ended a licencia, whose vacation was approved and whose shift changed, because each of those changes what is paid.

Content per RF-1: altas y bajas, inicio y fin de licencias, vacaciones aprobadas, and cambios de turno. The sources already exist: `users.contract_start_date` and `contract_end_date` give altas and bajas; `app/Models/Leave.php` with `LeaveType` and `LeaveStatus` covers licencias, vacaciones and permisos; `app/Models/ShiftAssignment.php` covers shift changes, and `app/Services/Reports/ShiftChangesReportService.php` already builds a shift-change report for the DT and should be read before writing a second one.

The output shape is specific and is the reason this is its own task: **Excel with one sheet per movement type**, which is why KOL-15 has multi-sheet support as a requirement. On screen this is naturally a tabbed or grouped view over the same data.

The subtlety worth thinking about is what 'in the period' means for each movement — a licencia that started before the period and ends inside it is a movement for this period; so is one that starts inside and runs past the end. Boundary handling should be explicit, not incidental.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The report lists altas, bajas, licencia starts and ends, approved vacations and shift changes falling in the selected period
- [x] #2 Each movement type is a separate sheet in the exported Excel workbook
- [x] #3 The on-screen view presents the same movement types in a grouped or tabbed layout over the same data
- [x] #4 Movements that straddle the period boundary in either direction are included according to an explicit documented rule, and tests cover both directions
- [x] #5 Shift change detection reuses or is consistent with the existing DT shift-changes report rather than defining a second notion of a change
- [x] #6 A period with no movements of a given type still produces that sheet, empty and labelled, rather than omitting it
- [x] #7 The export is recorded in the export audit history
- [x] #8 All sheet names and headings are in Spanish
- [x] #9 Pest tests cover each movement type, the boundary cases, and the multi-sheet structure of the export
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
1. Backend: PeriodMovementsReportBuilder (altas/bajas from contract_start_date/contract_end_date; inicio/fin de licencias from Leave.start_date/end_date, split by column so boundary-straddling is handled per-edge; vacaciones aprobadas from Leave type=Vacation overlapping the period; cambios de turno reusing ShiftChangesReportService verbatim).
2. Backend: PeriodMovementsReportExporter using ReportWriter::excelSheets() — one Blade fragment per movement type, reusing exports/dt/shift-changes.blade.php for shift changes (AC #5).
3. Backend: PeriodMovementsReportController (index + export), routes under payroll-reports/period-movements, gated by existing View/Export:PayrollReport permissions, audited via PayrollExportReadinessService::recordExport.
4. Blade fragments: exports/payroll/movements/{hires,terminations,leave-starts,leave-ends,vacations}.blade.php, each rendering its table even when empty (AC #6).
5. Frontend: types.ts additions, period-movements.tsx page with a Tabs view over the 6 movement types, reusing ReportFilterForm/PeriodSelector; sidebar nav entry.
6. Translations: es/en ui.php movements.* keys (tabs, columns, empty states).
7. wayfinder:generate, Pest tests (PeriodMovementsReportControllerTest: each movement type, both boundary directions, empty-sheet labelling, multi-sheet xlsx structure, audit log, permissions, tenant scoping), pint, sa test.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented: PeriodMovementsReportBuilder (altas/bajas from contract dates; inicio/fin de licencias filtered independently on start_date resp. end_date, which naturally handles boundary-straddling in both directions per AC #4 with no special-casing; vacaciones aprobadas via range-overlap; cambios de turno reusing ShiftChangesReportService + its exports.dt.shift-changes Blade fragment verbatim for AC #5). PeriodMovementsReportExporter is Excel-only (per PRD's report table) via ReportWriter::excelSheets(), 6 sheets: Altas, Bajas, Inicio de Licencias, Fin de Licencias, Vacaciones Aprobadas, Cambios de Turno — each sheet renders even when empty (AC #6). Audit trail reuses PayrollExportReadinessService::recordExport (report_type 'period-movements'); no readiness/confirmation gate since KOL-14's checks target attendance data, not these movement types. Frontend: payroll-reports/period-movements.tsx with a Tabs view (6 tabs w/ counts) reusing ReportFilterForm/PeriodSelector; sidebar entry added. 15 new Pest tests (PeriodMovementsReportControllerTest) cover each movement type, both boundary directions, pending-vs-approved exclusion, empty-sheet labelling, 6-sheet xlsx structure, audit log, permissions, tenant scoping. pint clean, phpstan clean, npm run types:check clean, targeted Pest suite (period-movements + weekly-detail + payroll-summary + DT reports + ReportWriter, 107 tests) green.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added 'Movimientos del Período' (RF-1): PeriodMovementsReportBuilder computes 6 movement types (altas/bajas from contract dates; inicio/fin de licencias filtered independently on start_date vs end_date, which handles boundary-straddling in both directions with no special-casing; vacaciones aprobadas via range overlap; cambios de turno reusing ShiftChangesReportService and its DT Blade fragment verbatim). PeriodMovementsReportExporter produces a 6-sheet Excel workbook (Excel-only per the PRD) via ReportWriter::excelSheets(), each sheet rendering even when empty. Export is audited via the shared payroll_export activity log. On-screen view is a tabbed page over the same data, reusing the existing filter/period/employee-picker components. Verified with 15 new Pest tests (PeriodMovementsReportControllerTest: each movement type, both boundary directions, pending-vs-approved exclusion, empty-sheet labelling, 6-sheet xlsx structure with Spanish titles, audit log, permissions, tenant scoping) plus the full suite (1274/1281 passing, 7 skipped, 0 failed), pint, phpstan and npm run types:check all clean. User reviewed and approved in the browser.
<!-- SECTION:FINAL_SUMMARY:END -->
