---
id: KOL-20
title: 'Report: Resumen de Remuneraciones por Periodo'
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
- [ ] #1 The report renders one row per employee for the selected period with a consolidated company total
- [ ] #2 Columns cover horas normales, horas extra by bucket, atrasos, justified and unjustified absences, and the dias pagados / no pagados split
- [ ] #3 All figures come from the payroll aggregation service; no calculation logic is duplicated in the controller or the view
- [ ] #4 The report exports to Excel, CSV and PDF and every format shows the same figures as the screen
- [ ] #5 The integrity check runs before export and the user must confirm to proceed when there are unresolved items
- [ ] #6 The PDF is legible for a company of 200+ employees; the orientation and pagination choice is deliberate and noted
- [ ] #7 The export is recorded in the export audit history
- [ ] #8 The report respects every filter dimension including the exclusion selection
- [ ] #9 All headings and labels are in Spanish
- [ ] #10 Pest tests cover the row and total figures against a known fixture, each export format, and that the totals equal the sum of the rows
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
