---
id: KOL-30
title: Add cost centres as a reporting dimension
status: Done
assignee: []
created_date: '2026-08-04 18:59'
updated_date: '2026-08-04 22:24'
labels:
  - payroll-reports
  - backend
  - frontend
milestone: m-0
dependencies: []
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 8000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-7 of the payroll reports PRD requires every report to be filterable by *centro de costo*, and the Nubox hand-off (RF-4) is the point where an accountant splits payroll cost across the business. **No such concept exists anywhere in the codebase today** — a grep for cost_center/costCenter/centro_costo returns nothing. Employees carry `company_id`, `premise_id` and `position_id` only.

A cost centre is not the same as a premise (sucursal): two teams working out of one premise routinely charge to different cost centres, and a single cost centre can span premises. So this needs its own model rather than reusing `Premise`.

Scope it as a plain organizational catalogue owned by the tenant, in the same shape as `Position` (see `app/Models/Position.php`, `app/Http/Controllers/PositionController.php` and `resources/js/pages/positions` for the CRUD pattern this project already uses, including the organization scope). Employees reference one cost centre, optionally — existing employees must keep working with none assigned.

This lands before the report tasks because the filter layer (KOL-18-ish) and the payroll aggregation query both need the column to exist.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Company carries an optional 'código contable' the client can match to their own accounting system; it is unique within the organization and nullable so every existing company stays valid
- [x] #2 The accounting code is editable from the company form and visible/searchable on the company list
- [x] #3 The employee list surfaces the company and can be filtered by it, so payroll reports can segment by that dimension
- [x] #4 Deleting a company that still has employees assigned does not orphan or silently reassign them; the behaviour is explicit and tested
- [x] #5 Pest tests cover organization scoping of the code's uniqueness, the nullable/optional path, and the delete behaviour
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
REVISED (user, 2026-08-04): the Company/CostCenter rename was reverted. An 'empresa de servicios transitorios' flag makes no sense on a cost centre, so Company stays exactly what it is - the employer legal entity - and gains the payroll-report data instead. The centro-de-costo reporting dimension is served by Company + its código contable.

1. Migration: add nullable 'code' to companies (after social_reason). No renames, no other schema change.
2. Company model: 'code' in Fillable + docblock.
3. CompanyController: validate code (nullable, max:50, unique per organization ignoring self, empty string normalised to null); expose it on index (sortable column + included in search) and on the edit payload.
4. Frontend: code field in company-form.tsx, code column in pages/companies/index.tsx, passthrough in edit.tsx.
5. Employee list: company column + faceted filter, following the existing premises/positions idListFilter pattern.
6. Delete guard: refuse to delete a company that still has employees assigned (flash error); legal reps are owned by the company and go with it.
7. lang es/en: companies.columns.code, companies.form.code + code_hint, companies.flash.delete_blocked, employees.columns.company, employees.filters.company; validation attribute for 'code'.
8. Pest tests for code uniqueness per tenant, optional code, delete guard, employee filter.

Deliberately NOT adding speculative payroll fields: re-read docs/prd-reports.md RF-1/RF-4/RF-7 - the only company-level datum the payroll reports need that does not already exist is the accounting code. RUT and razón social are already on the model. Ask the user before inventing more.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Approach: Company keeps its identity as the employer legal entity and gains the payroll-report data. No CostCenter model — a rename was tried first and reverted, because attributes like is_est (empresa de servicios transitorios) are legal-entity facts that make no sense on an accounting bucket.

Schema: one migration, 2026_08_04_220748_add_code_to_companies_table — a single nullable 'code' column. Nothing renamed, nothing else touched.

Scope note on 'cualquier otro dato necesario para los reportes de nomina': re-read docs/prd-reports.md RF-1, RF-4 and RF-7. The only company-level datum the payroll reports need that did not already exist is the código contable. RF-4's Nubox layout (PERIODO / FUNCIONARIO / CODIGO DE HABER DESCUENTO / MONTO) matches on the employee RUT and on codes the client configures in their own Nubox, so it needs nothing further from Company; RUT and razón social are already on the model. No speculative fields added — waiting on the user for anything specific.

Behaviour added beyond the column: CompanyController::destroy now refuses to delete a company that still has employees assigned (flash error, nothing deleted) instead of orphaning them behind a soft-deleted row; legal reps are owned by the company and go with it. The employee list gained a company column and a faceted company filter so reports can segment by that dimension.

Docs: new 'Company as the cost-centre dimension' section in docs/architecture.md recording the decision and the two constraints that make the legal fields load-bearing (DT employer column / MarkObserver snapshot; {{company_*}} contract variables), so the CostCenter idea is not re-proposed.

Checks: pint clean, phpstan clean, tsc clean, 662 tests / 658 passed / 4 skipped. Prettier warnings 16 vs 17 on master; the 2 eslint errors are pre-existing (use-server-table.ts).

Verification evidence per criterion (664 tests / 660 passed / 4 skipped):
AC1 - 'the accounting code is unique within the organization but not across tenants' (asserts a duplicate within the org fails validation while another tenant's code is accepted) and 'the accounting code is optional and stored as null when blank'.
AC2 - 'the edit page hands the accounting code to the form' (Inertia prop company.code), 'the companies index exposes the accounting code per row', 'the companies index can be searched by accounting code', 'a company keeps its own accounting code when updated'.
AC3 - 'employees can be filtered by company' and 'the employees list surfaces the company each employee belongs to'.
AC4 - 'a company with employees assigned cannot be deleted' (asserts the company is NOT soft-deleted and the employee still points at it) and 'deleting a company also removes its legal representatives'.
AC5 - the org-scoping, nullable and delete tests listed above all live in tests/Feature/CompanyManagementTest.php.
DoD - pint clean, sa test --compact passes, npm run types:check passes, phpstan clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added an optional código contable to Company (nullable, unique per organization) as the payroll reports' cost-centre identifier, rather than introducing a separate CostCenter model — a rename to CostCenter was tried and reverted because legal-entity attributes like is_est make no sense on an accounting bucket. Also guarded company deletion against orphaning assigned employees, and surfaced company as a column and faceted filter on the employee list. Verified with 664 Pest tests (660 passed, 4 skipped) covering per-tenant code uniqueness, the nullable path, the edit/index payloads, code search and the delete guard; pint, phpstan and tsc all clean.
<!-- SECTION:FINAL_SUMMARY:END -->
