---
id: KOL-23
title: 'Report: Maestro de Trabajadores'
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-04 11:14'
updated_date: '2026-08-30 20:16'
labels:
  - payroll-reports
  - backend
  - frontend
  - report
milestone: m-0
dependencies:
  - KOL-10
  - KOL-15
  - KOL-19
  - KOL-30
documentation:
  - docs/prd-reports.md
priority: medium
type: feature
ordinal: 22000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-1, matching Talana's 'Maestro de Empleados'. A bulk dump of the employee master file, which is what an accountant loads first when setting a client up in a payroll system — before any hours matter, they need the people, their RUTs and where each one belongs.

Content per RF-1: the full ficha plus the current contract, sucursal and centro de costo. `users` already carries name parts, RUT, contract dates, position, premise, company, nationality, gender, phone, emergency contact and active flag; KOL-30 and KOL-10 add cost centre and contract type, which is why this depends on both.

Formats: Excel and CSV. This is the report most likely to be fed straight into another system, so the CSV correctness work in KOL-15 matters here most — RUT formatting in particular. Decide deliberately whether RUT is exported with dots and hyphen or bare, and be consistent, because it is the join key on the other side; `app/Support/Rut.php` already exists and should be the single authority for the formatting.

This report contains personal data and no attendance figures, so it is worth confirming during implementation whether it should sit behind a stricter permission than the hours reports.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The report exports the employee master with identity, RUT, contract dates and type, position, premise, company and cost centre
- [x] #2 RUT formatting goes through the existing Rut support class and is consistent across every row and format
- [x] #3 The report exports to Excel and CSV, with the CSV opening cleanly in Excel under a Chilean locale
- [x] #4 Inactive employees are handled explicitly: either excluded or flagged, per a documented decision rather than by accident
- [x] #5 Whether this report needs a stricter permission than the hours reports is decided and the reasoning recorded in the notes
- [x] #6 The export is recorded in the export audit history
- [x] #7 The report respects the shared filters and is organization-scoped
- [x] #8 All headings are in Spanish
- [x] #9 Pest tests cover the column set, RUT formatting, the inactive-employee rule and both export formats
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
1. Add Export button (Excel/CSV) to the /employees DataTable toolbar instead of a new payroll-reports/* page — this report has no period dimension and EmployeeController@index already carries the exact filters/org-scoping AC#7 needs.
2. Backend: extract EmployeeController's existing where/search/filter chain into a private applyEmployeeFilters(Builder, Request) helper, reused unchanged by index() (paginated, summary columns) and a new export() action (full ficha, unpaginated, respecting the same request-driven filters/sort).
3. Add route employees/export/{format} (GET) inside the existing role:admin group, named employees.export — no new Spatie permission; record in notes that role:admin already gates this stricter than permission:View:PayrollReport gates the other reports (AC#5).
4. New App\Services\Reports\EmployeeMasterExporter (mirrors PayrollSummaryReportExporter): FORMATS=['excel','csv'], renders one Blade fragment (resources/views/exports/employees/master.blade.php) with full ficha columns in Spanish, formatted RUT via the existing formatted_rut accessor (Rut::format, AC#2), wrapped for Excel via a dedicated exports/employees/document.blade.php shell (own copy, matching the existing dt->payroll precedent of not sharing shells across domains) and via ReportWriter::csv() with ';' delimiter for CSV (Chilean locale, AC#3).
5. AC#4 decision: do NOT silently exclude inactive employees — export honors whatever is_active filter the table currently has applied (default: all) and always renders an explicit 'Activo' Sí/No column, so inactive rows are visible/flagged rather than dropped by surprise. Record this reasoning in task notes.
6. AC#6 audit trail: call PayrollExportReadinessService::recordExport() from EmployeeController::export() (reportType 'employee-master', start=end=now() since there's no period, confirmed=true, no readiness check needed — no attendance figures involved) so the export lands in the same payroll_export activity log every other report writes to.
7. Frontend: add an Export dropdown (Excel/CSV) to employees/index.tsx, fetch+blob download reusing window.location.search (same pattern as payroll-reports/period-movements.tsx) so the download always matches the table's current filters/search/sort.
8. Add Spanish i18n keys under ui.employees.export.*; regenerate Wayfinder (--with-form) after adding the route.
9. Pest tests in tests/Feature/EmployeeManagementTest.php: column set + RUT formatting + inactive-employee visibility + both formats + filters respected + audit log entry, following PeriodMovementsReportControllerTest's spreadsheet-reading pattern.
10. vendor/bin/pint --dirty, sa test --compact, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implementation complete (backend + frontend). AC#5: no new Spatie permission — /employees already sits under role:admin (routes/web.php), stricter than permission:View:PayrollReport gating the other three RF-1 reports. AC#4: inactive employees are never silently excluded — the export honors whatever is_active filter the table currently has (default: all) and always renders an explicit Activo Sí/No column, so exclusion is only ever a deliberate filter choice, never an accident. AC#6: audit trail reuses PayrollExportReadinessService::recordExport() with reportType 'employee-master' and start=end=export timestamp (no readiness check — no attendance figures involved), landing in the same payroll_export activity log every other report writes to. Deliberately dropped the <h1> title row other payroll export fragments use: this report is explicitly the one most likely to be machine-consumed downstream, so CSV/Excel open with the column header on row one. Pest: 51/51 in EmployeeManagementTest (10 new tests covering column set, RUT formatting, inactive-employee flagging, filter/search/org-scoping propagation, CSV delimiter, audit log). pint clean, npm run types:check clean.

Full suite verified clean post-approval: sa test --compact -> 1283/1290 passed, 7 skipped, 0 failures (554.7s). User confirmed the Export dropdown, both formats, and inactive-employee flagging in the browser via composer run dev.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added an Export dropdown (Excel/CSV) to the /employees table instead of a new payroll-reports page, since Maestro de Trabajadores is a snapshot with no period dimension and the table's existing filters already satisfy AC#7. Backend: EmployeeController::export() reuses the same filtered/org-scoped query as index() via a shared filteredEmployeesQuery() helper, a new EmployeeMasterExporter renders the full ficha (RUT via the existing formatted_rut accessor) to Excel/CSV, inactive employees are included and flagged (never silently dropped), and every export is logged to the shared payroll_export activity log. No new permission — /employees already sits behind role:admin, stricter than the other reports' View:PayrollReport gate. Verified with 51/51 Pest tests in EmployeeManagementTest (10 new), the full suite (1283/1290, 7 skipped unrelated), pint, npm run types:check, and manual browser confirmation.
<!-- SECTION:FINAL_SUMMARY:END -->
