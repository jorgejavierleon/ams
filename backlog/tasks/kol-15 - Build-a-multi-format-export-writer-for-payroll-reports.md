---
id: KOL-15
title: Build a multi-format export writer for payroll reports
status: To Do
assignee: []
created_date: '2026-08-04 11:12'
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
- [ ] #1 Payroll reports can be exported to xlsx, csv and pdf through a shared writer, without duplicating per-report formatting logic
- [ ] #2 No new Composer dependency is added; the existing phpspreadsheet, phpword and dompdf packages are used
- [ ] #3 The five DT reports still export identically to before, proven by their existing tests continuing to pass unchanged
- [ ] #4 CSV output is UTF-8 with a BOM and the delimiter is selectable between comma and semicolon at export time
- [ ] #5 A CSV containing acentos, eñes and a RUT opens correctly in Excel under a Chilean locale with the semicolon setting
- [ ] #6 The writer supports an Excel workbook with multiple named sheets, for the Movimientos report
- [ ] #7 Exported files are named consistently and identifiably (report, period, organization) following the DT exporter's convention
- [ ] #8 Pest tests assert each format is produced, that content matches across formats for the same report, and cover the BOM and delimiter behaviour
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
