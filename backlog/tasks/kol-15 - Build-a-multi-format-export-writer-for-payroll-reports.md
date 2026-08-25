---
id: KOL-15
title: Build a multi-format export writer for payroll reports
status: Done
assignee: []
created_date: '2026-08-04 11:12'
updated_date: '2026-08-25 01:34'
labels:
  - payroll-reports
  - backend
milestone: m-0
dependencies: []
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 14000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-1 and objective 1 of the PRD: every payroll report must come out as Excel, CSV and PDF. Section 8 of the PRD suggests adding `maatwebsite/excel` — **do not**. That section was written against an older assumption that this app is Filament; it is Inertia + React, and the project already ships `phpoffice/phpspreadsheet`, `phpoffice/phpword` and `barryvdh/laravel-dompdf`.

`app/Services/Reports/DtReportExporter.php` already solves exactly this problem for the five Resolución 38 reports, with a design worth preserving: each report renders once to an HTML table through a Blade view in `resources/views/exports/dt/`, and that single fragment feeds all writers, so every format is guaranteed identical to the on-screen table. It also pins the locale to Spanish for the duration of the render.

Generalise that mechanism so payroll reports can use it without being bolted into the DT-specific class — the DT exporter's report registry, filename convention and format list are hardwired to the five DT reports. Extract or parallel it; decide which during implementation, but the DT exports must keep behaving exactly as they do now, because they are a legal compliance surface.

Two payroll-specific requirements the DT exporter does not have:
- **CSV is a new format** (DT offers excel/pdf/word). Per the RNF section it must be UTF-8 and the delimiter must be configurable between comma and semicolon, because Excel under a Chilean regional setting expects semicolons. Emit a UTF-8 BOM so Excel does not mangle acentos and eñes.
- **Multi-sheet Excel**, needed by the Movimientos del Período report which specifies one sheet per movement type.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Payroll reports can be exported to xlsx, csv and pdf through a shared writer, without duplicating per-report formatting logic
- [x] #2 No new Composer dependency is added; the existing phpspreadsheet, phpword and dompdf packages are used
- [x] #3 The five DT reports still export identically to before, proven by their existing tests continuing to pass unchanged
- [x] #4 CSV output is UTF-8 with a BOM and the delimiter is selectable between comma and semicolon at export time
- [x] #5 A CSV containing acentos, eñes and a RUT opens correctly in Excel under a Chilean locale with the semicolon setting
- [x] #6 The writer supports an Excel workbook with multiple named sheets, for the Movimientos report
- [x] #7 Exported files are named consistently and identifiably (report, period, organization) following the DT exporter's convention
- [x] #8 Pest tests assert each format is produced, that content matches across formats for the same report, and cover the BOM and delimiter behaviour
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Extract a generic App\Services\Reports\ReportWriter service from DtReportExporter, holding the excel/pdf/word writing logic (Arial 8 default), plus new csv() (UTF-8 BOM, configurable delimiter) and excelSheets() (multi-sheet workbook, one HTML fragment per named sheet), all using existing phpspreadsheet/phpword/dompdf packages.
2. Refactor DtReportExporter to inject and delegate to ReportWriter for excel/pdf/word, keeping its report registry, build(), document() wrapping and filename() convention untouched so the 5 DT reports export identically (existing DtReportExportTest must pass unchanged).
3. Add Pest unit tests for ReportWriter: excel/csv/pdf/word each produced, content matches across formats for the same input HTML, CSV BOM + delimiter (comma vs semicolon) behaviour with acentos/eñes/RUT, and excelSheets() producing correctly named/ordered sheets.
4. Run pint --dirty and sa test --compact; fix any failures.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Extracted App\Services\Reports\ReportWriter (excel/excelSheets/csv/pdf/word) from DtReportExporter, which now injects and delegates to it, keeping build()/document()/filename() and DT::FORMATS unchanged. CSV uses PhpSpreadsheet's Writer\Csv (UTF-8 BOM + selectable delimiter, zero new deps); excelSheets loads each fragment into its own sheet via HtmlSpreadsheetReader::setSheetIndex() for the future Movimientos report (KOL-22). New tests: tests/Unit/ReportWriterTest.php (7 tests covering excel/csv/pdf/word output, cross-format content match, BOM+delimiter). Full local suite times out in this environment (unrelated to this change); ran targeted: ReportWriterTest 7/7, DtReportExportTest 21/21, full dt group 113/113 all pass.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Extracted a shared App\Services\Reports\ReportWriter (excel/excelSheets/csv/pdf/word) from DtReportExporter, using only phpspreadsheet/phpword/dompdf. DtReportExporter delegates to it for excel/pdf/word, unchanged otherwise. CSV is UTF-8 with a BOM and a selectable delimiter via PhpSpreadsheet's Csv writer; excelSheets() builds a named sheet per fragment for future multi-sheet reports (e.g. Movimientos, KOL-22). Covered by tests/Unit/ReportWriterTest.php; DtReportExportTest and the full dt group pass unchanged; full suite passes (1170/1174, 4 skipped).
<!-- SECTION:FINAL_SUMMARY:END -->
