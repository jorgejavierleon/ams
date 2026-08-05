---
id: KOL-10
title: Record contract type on the employee record
status: Done
assignee: []
created_date: '2026-08-04 11:10'
updated_date: '2026-08-05 22:18'
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
- [x] #1 A ContractType enum exists following the conventions of the existing enums in app/Enums, including translated labels and an options() helper for select inputs
- [x] #2 Employees carry a nullable contract type; the migration does not guess a value for existing rows
- [x] #3 The contract type is editable on the employee form and visible on the employee view page, in Spanish
- [x] #4 The employee list can be filtered by contract type
- [x] #5 The choice about how honorarios workers are represented is recorded in the task notes with its reasoning, and matches what the code does
- [x] #6 The employee factory can produce each contract type so report tests can build mixed populations
- [x] #7 Pest tests cover persisting and updating the field and filtering the employee list by it
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
1. Add App\Enums\ContractType (Indefinido, PlazoFijo, PorObraOFaena, Honorarios) with label() reading ui.employees.contract_types.*, options(), and isEmploymentContract() so payroll consumers can exclude honorarios from the enum itself rather than a parallel flag.
2. Migration: nullable string contract_type on users, after contract_end_date, no default/backfill.
3. User model: fillable + ContractType cast + PHPDoc property.
4. EmployeeController: validate nullable Rule::enum, expose on show/edit, add contractTypes faceted filter on index (enumListFilter) plus contractTypeOptions, and surface the label on the list row.
5. Frontend: contract type Combobox in the Labor tab of employee-form, faceted filter + column on employees/index, Field on employees/show.
6. es/en translations under employees.contract_types and employees.form/filters/columns.
7. UserFactory::contractType(ContractType) state.
8. Pest coverage: create/update persists it, invalid value rejected, index filters by it, show exposes it.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Honorarios decision (AC #5): 'honorarios' is a case of ContractType, not a separate boolean flag.

Reasoning: a parallel flag is a second source of truth that can contradict the contract type (an employee flagged honorarios while carrying contract_type=indefinido), whereas one column answering 'what is this person's engagement' cannot. Honorarios workers are still real people who clock in and belong on the RF-1 Maestro de Trabajadores roster, so excluding them from the catalogue entirely would leave their engagement unrecorded.

What the code does: ContractType::isEmploymentContract() returns false only for Honorarios, and ContractType::employmentContractCases() returns the three contratos de trabajo. Payroll-shaped consumers (attendance-derived exports) must filter through those rather than hard-coding case lists. Covered by the test 'honorarios is the only contract type that is not an employment contract' and documented in docs/architecture.md (## Contract type).

Column is nullable with no backfill; reports must treat null as unknown, not as indefinido.

Validation: sail artisan test --compact -> 718 tests, 714 passed, 4 skipped (pre-existing); EmployeeManagementTest 35 passed / 217 assertions including the 9 new contract-type tests. vendor/bin/pint --dirty clean, npm run types:check clean, eslint clean on the touched React files. Migration applied locally.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added App\Enums\ContractType (indefinido, plazo fijo, por obra o faena, honorarios) and a nullable, un-backfilled users.contract_type column, wired through EmployeeController (nullable Rule::enum validation, contractTypes index filter, options for the form), the employee form/list/detail pages in Spanish, es/en translations and a UserFactory::contractType() state. Honorarios lives in the enum rather than a parallel flag; payroll consumers exclude it via isEmploymentContract(), documented in docs/architecture.md. Verified by 9 new Pest tests covering persistence, update, rejection of unknown values, list filtering and the show/edit payloads, with the full suite, Pint and tsc green.
<!-- SECTION:FINAL_SUMMARY:END -->
