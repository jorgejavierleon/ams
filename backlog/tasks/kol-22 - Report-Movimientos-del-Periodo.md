---
id: KOL-22
title: 'Report: Movimientos del Periodo'
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
- [ ] #1 The report lists altas, bajas, licencia starts and ends, approved vacations and shift changes falling in the selected period
- [ ] #2 Each movement type is a separate sheet in the exported Excel workbook
- [ ] #3 The on-screen view presents the same movement types in a grouped or tabbed layout over the same data
- [ ] #4 Movements that straddle the period boundary in either direction are included according to an explicit documented rule, and tests cover both directions
- [ ] #5 Shift change detection reuses or is consistent with the existing DT shift-changes report rather than defining a second notion of a change
- [ ] #6 A period with no movements of a given type still produces that sheet, empty and labelled, rather than omitting it
- [ ] #7 The export is recorded in the export audit history
- [ ] #8 All sheet names and headings are in Spanish
- [ ] #9 Pest tests cover each movement type, the boundary cases, and the multi-sheet structure of the export
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
