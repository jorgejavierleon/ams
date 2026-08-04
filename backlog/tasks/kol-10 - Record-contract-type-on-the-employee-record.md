---
id: KOL-10
title: Record contract type on the employee record
status: To Do
assignee: []
created_date: '2026-08-04 11:10'
labels:
  - payroll-reports
  - backend
  - frontend
milestone: m-0
dependencies: []
documentation:
  - docs/prd-reports.md
priority: medium
type: feature
ordinal: 9000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-7 requires filtering reports by *tipo de contrato*, and RF-1's Maestro de Trabajadores report is specified as 'ficha completa + último contrato vigente'. The users table carries `contract_start_date` and `contract_end_date` but **nothing that says what kind of contract it is**.

Chilean labour law distinguishes contract types that matter directly to how payroll treats a worker — indefinido, plazo fijo, and por obra o faena are the ones the Código del Trabajo recognises for dependent workers. Honorarios (boleta) workers are not on a contrato de trabajo at all and generally should not appear in an attendance-derived payroll export; decide during implementation whether they belong in the enum or are excluded by a separate flag, and write down the reasoning in the task notes.

Follow the existing enum convention in `app/Enums` (TitleCase cases, string backing values, a `label()` reading from `ui.*` translations and an `options()` helper — `app/Enums/WorkdayStatus.php` and `app/Enums/LeaveType.php` are the models to copy).

The field is nullable: existing employees predate it and must not be forced into a wrong value by a migration default.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A ContractType enum exists following the conventions of the existing enums in app/Enums, including translated labels and an options() helper for select inputs
- [ ] #2 Employees carry a nullable contract type; the migration does not guess a value for existing rows
- [ ] #3 The contract type is editable on the employee form and visible on the employee view page, in Spanish
- [ ] #4 The employee list can be filtered by contract type
- [ ] #5 The choice about how honorarios workers are represented is recorded in the task notes with its reasoning, and matches what the code does
- [ ] #6 The employee factory can produce each contract type so report tests can build mixed populations
- [ ] #7 Pest tests cover persisting and updating the field and filtering the employee list by it
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
