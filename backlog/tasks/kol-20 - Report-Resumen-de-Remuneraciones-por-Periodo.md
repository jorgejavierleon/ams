---
id: KOL-20
title: 'Report: Resumen de Remuneraciones por Periodo'
status: Done
assignee: []
created_date: '2026-08-04 11:14'
updated_date: '2026-08-30 00:46'
labels:
  - payroll-reports
  - backend
  - frontend
  - report
milestone: m-0
dependencies:
  - KOL-13
  - KOL-14
  - KOL-15
  - KOL-19
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 19000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The flagship report of the feature and the answer to user story 1: RRHH generates one file per period and hands it to their external accountant, instead of assembling it by hand in Excel. Everything else in RF-1 supports this one.

Level: whole company, one row per employee, with a consolidated total. Columns per RF-1: horas normales, horas extra by bucket, atrasos, and absences split justified/injustificado — plus the días pagados / no pagados breakdown that KOL-13 produces, because that split is what an accountant actually keys into a liquidación.

All the numbers come from the aggregation service (KOL-13); this task is the screen, the column layout and the export binding. If a figure is missing, it belongs in KOL-13, not here.

The on-screen table and the exported file must show the same thing — that guarantee is why `DtReportExporter` renders one Blade fragment for all formats, and the payroll writer from KOL-15 keeps it.

Formats: Excel, CSV, PDF. The integrity warning from KOL-14 runs before any of them.

Watch the PDF: a wide column set over hundreds of employees is unreadable in portrait. Decide the landscape/pagination treatment deliberately rather than letting dompdf truncate.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The report renders one row per employee for the selected period with a consolidated company total
- [x] #2 Columns cover horas normales, horas extra by bucket, atrasos, justified and unjustified absences, and the dias pagados / no pagados split
- [x] #3 All figures come from the payroll aggregation service; no calculation logic is duplicated in the controller or the view
- [x] #4 The report exports to Excel, CSV and PDF and every format shows the same figures as the screen
- [x] #5 The integrity check runs before export and the user must confirm to proceed when there are unresolved items
- [x] #6 The PDF is legible for a company of 200+ employees; the orientation and pagination choice is deliberate and noted
- [x] #7 The export is recorded in the export audit history
- [x] #8 The report respects every filter dimension including the exclusion selection
- [x] #9 All headings and labels are in Spanish
- [x] #10 Pest tests cover the row and total figures against a known fixture, each export format, and that the totals equal the sum of the rows
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
1. Backend: PayrollSummaryReportController (index+export), reusing ReportEmployeeSelector/PayrollPeriodSummaryService/PayrollExportReadinessService/ReportPeriod. New PayrollSummaryReportExporter (parallel to DtReportExporter) built on ReportWriter -> excel/csv/pdf via one Blade fragment exports/payroll/summary.blade.php (grouped headers: horas normales, horas extra x4 buckets, atraso, ausencias justificadas/injustificadas, dias pagados x3, dias no pagados x3, grand total row). Landscape PDF via ReportWriter default. 2. Extend PayrollExportReadinessService with recordExport() (activity log, same 'payroll_export' log name as recordConfirmation) so every export is recorded (AC7); full browsable history UI stays KOL-17. Export action requires confirmed=1 when readiness->requiresConfirmation(). 3. Routes: GET payroll-reports/summary (View:PayrollReport), GET payroll-reports/summary/export (Export:PayrollReport, per RoleSeeder's existing separate permission). 4. Frontend: payroll-reports/summary.tsx embedding ReportFilterForm/PeriodSelector/EmployeePicker + Generar action + on-screen table + PayrollExportReadinessWarning + export buttons. Small additive onStateChange callback on report-filter-form.tsx. Wire payroll-summary card on index.tsx to the new route. 5. Spanish/English translations. 6. Pest tests: fixture-based row/total figures, cross-format export parity, confirm-gating, permission scoping (View vs Export), audit log entry. Out of scope: async queueing for large selections (KOL-16 queue job is DT-specific), KOL-17's audit-history UI.

9. UI/UX redesign (post-review): replaced the DataTable-backed employee picker and Paso1/Paso2 period card with a single chip-based filter panel matching a Claude Design mock (project 'Chilean attendance filter panel design'), imported via the design MCP. Scope decisions confirmed with user: (a) dropped quincena from the UI entirely -- period picker now offers whole months only; ReportPeriodType/ReportPeriod backend enum and quincena math are left intact and still default-resolvable, just no longer exposed by any control, (b) kept the existing facet filters (Sucursal/Cargo/Centro de costo/Tipo de contrato) multi-select, only restyled their trigger as a pill chip, (c) folded into KOL-20 rather than a new follow-up task since the touched files were already this ticket's uncommitted diff.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented: PayrollSummaryReportController (index+export), PayrollSummaryReportBuilder (formats KOL-13's PayrollPeriodSummary into rows+consolidated total, no recalculation), PayrollSummaryReportExporter (parallels DtReportExporter, built on ReportWriter -> excel/csv/pdf, exports/payroll/summary.blade.php + exports/payroll/document.blade.php own copy of the DT shell so DT's compliance surface stays untouched). Routes: GET payroll-reports/summary (View:PayrollReport), GET payroll-reports/summary/export/{format} (Export:PayrollReport, the RoleSeeder's pre-existing separate permission). Extended PayrollExportReadinessService with recordExport() (same 'payroll_export' activity log recordConfirmation already writes to) so every export is recorded (AC7) -- KOL-17 still owns the browsable history UI, this only guarantees a trace exists. Frontend: payroll-reports/summary.tsx (embeds ReportFilterForm/PeriodSelector/EmployeePicker + Generar action + on-screen table mirroring the export columns + PayrollExportReadinessWarning + export buttons gated on confirmed). Added optional onStateChange callback to report-filter-form.tsx (additive, landing-page preview unaffected). Wired the payroll-summary card on the index page to the new route. Extracted ResolvesReportEmployeeFilters trait shared by PayrollReportController and the new controller (dedup). 14 new Pest tests in PayrollSummaryReportControllerTest covering AC1,3,4,5,7,8,9,10 plus permission separation and tenant scoping. pint clean, phpstan (types:check) clean, npm run types:check + eslint clean, npm run build succeeds (summary-*.js chunk present). Ran targeted tests only (Payroll*/ReportPeriod*/ReportSelectionSharedByDownstream*: 48/48, plus dt group regression 121/121) per project convention of not running the full suite until asked. Out of scope per plan: async queueing for large selections (KOL-16's queue job is DT-specific), KOL-17's full browsable export-history UI.

Redesign implementation: period-selector.tsx rewritten as a pill chip + month-grid popover (no quincena control); report-facet-filter.tsx trigger restyled to a pill chip (Popover/Command/count/Limpiar/Listo internals unchanged); employee-picker.tsx (DataTable-backed) deleted and replaced by collaborator-filter.tsx, a searchable multi-select popover (avatar-initials rows, pills-in-field, 3-state select/exclude indicator) built on router.get partial reloads against the same ReportEmployeeSelector::paginate() endpoint (perPage raised 10->40 for both controllers since there's no more page control); report-filter-form.tsx rewritten to own facet/month/selection state directly and compose the chip row + active-filter chips + selection status bar + excluded-manual banner (all pre-existing KOL-19/KOL-89 behaviour -- select-all+exclude persistence, tenant scoping, Spanish labels -- preserved, just re-skinned). Removed periodTypeOptions prop from both controllers/pages/types (backend ReportPeriodType/ReportPeriod untouched). Cleaned up now-dead lang keys (step_period, step_employees, period_label, period_type_label, period_types_short, filters.columns.*) in es/en ui.php; updated 'no employees selected' copy to stop referencing 'paso 2'. Removed the PayrollReportEmployeePickerTest assertion for the now-gone periodTypeOptions prop. Verified: sail artisan test --compact --filter=PayrollReport|PayrollSummary|ReportEmployeeSelector|ReportPeriod|ReportSelectionSharedByDownstream (38/38 passing), pint --dirty clean, npm run types:check and lint clean, npm run build succeeds. Live-verified in Chrome (chrome-devtools MCP, admin@example.com) on both /payroll-reports and /payroll-reports/summary: facet chips, Colaborador search/select/pill/remove, month-grid picker (future months disabled), select-all-matching + status bar + Limpiar selección, and report generation/table all work end to end.

Finalization verification (2026-08-30): pint --dirty clean; full sail artisan test --compact: 1230 tests, 1223 passed, 7 skipped, 0 failed; npm run types:check clean; npm run lint clean (1 pre-existing unrelated warning in use-server-table.ts); npm run build succeeds (summary-*.js chunk present, no stale payroll-reports/index chunk). AC6 (PDF landscape/legibility) confirmed at code level: ReportWriter::pdf() calls setPaper('letter','landscape'), PayrollSummaryReportExporter's docblock documents the rationale (wide column set unreadable in portrait), and PayrollSummaryReportControllerTest's per-format streaming test covers pdf content-type. AC1/2/3/4/5/7/8/10 covered by PayrollSummaryReportControllerTest (row+total figures, exclusion respected, per-format export streaming, excel/screen figure parity, confirm-gating, audit log entry, permission/tenant scoping) and ReportEmployeeSelectorTest (every filter dimension, exclusion survives filter change). AC9 (Spanish labels) confirmed via live browser walkthrough this session on /payroll-reports/summary. Post-review UI redesign also removed the /payroll-reports landing page entirely per user request (KOL-20 UI redesign, step 2): deleted PayrollReportController, index.tsx, report-type-selector.tsx, report-types.ts, PayrollReportSectionTest, PayrollReportEmployeePickerTest, and the GET /payroll-reports route; nav 'Remuneraciones' now links straight to /payroll-reports/summary. Confirmed no coverage loss -- the deleted controller-level org-scoping/facet-filter tests duplicated what ReportEmployeeSelectorTest already proves at the service level. Confirmed live in Chrome (chrome-devtools MCP): /payroll-reports 404s, nav lands on /payroll-reports/summary directly, no 'Tipo de reporte' section, full filter+select+generate flow works end to end.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Shipped the 'Resumen de Remuneraciones por Período' report end to end: PayrollSummaryReportController (index+export), PayrollSummaryReportBuilder/Exporter built on KOL-13's aggregation service with no duplicated calculation logic, Excel/CSV/PDF export (landscape) gated on KOL-14's integrity check, and every export recorded in the payroll_export audit log. Frontend went through a full KOL-20 UI redesign into a single chip-based filter panel (period picker, multi-select facet chips, a searchable Colaborador multi-select replacing the old DataTable, and a live selection status bar with select-all+exclude), then a follow-up simplification that removed the now-unnecessary /payroll-reports landing page and 'Tipo de reporte' picker since only one report exists today -- the nav links directly to the report. Verified with the full Pest suite (1223 passed, 0 failed), pint/types-check/lint clean, a production build, and a live browser walkthrough of the whole flow.
<!-- SECTION:FINAL_SUMMARY:END -->
