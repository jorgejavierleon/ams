---
id: KOL-23
title: 'Report: Maestro de Trabajadores'
status: To Do
assignee: []
created_date: '2026-08-04 11:14'
updated_date: '2026-08-04 19:00'
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
- [ ] #1 The report exports the employee master with identity, RUT, contract dates and type, position, premise, company and cost centre
- [ ] #2 RUT formatting goes through the existing Rut support class and is consistent across every row and format
- [ ] #3 The report exports to Excel and CSV, with the CSV opening cleanly in Excel under a Chilean locale
- [ ] #4 Inactive employees are handled explicitly: either excluded or flagged, per a documented decision rather than by accident
- [ ] #5 Whether this report needs a stricter permission than the hours reports is decided and the reasoning recorded in the notes
- [ ] #6 The export is recorded in the export audit history
- [ ] #7 The report respects the shared filters and is organization-scoped
- [ ] #8 All headings are in Spanish
- [ ] #9 Pest tests cover the column set, RUT formatting, the inactive-employee rule and both export formats
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
