---
id: KOL-32
title: >-
  Constrain each organization to one company and add cost centres as their own
  model
status: Done
assignee: []
created_date: '2026-08-05 10:37'
updated_date: '2026-08-05 15:40'
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
ordinal: 31000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The data model is currently self-contradictory about what an employer is. docs/architecture.md:86 states the employer identity a DT inspector audits (rut, email, phone, address; razon social = name) lives on Organization "since one organization represents one employer", and defers retiring Company to a later ticket. docs/architecture.md:133 states the opposite — that a tenant may hold several companies. KOL-30 shipped on the second reading and bolted the payroll "codigo contable" onto companies, putting an accounting-bucket attribute on the employer legal entity.

This task settles it in the first direction, which is the one the DT code already took: **one organization = one employer**. Company stops being a CRUD catalogue and becomes account configuration — a single employer profile per tenant (RUT, razon social, giro, company_type, is_est, legal representatives). The many-to-one dimension the payroll reports call *centro de costo* becomes its own model, which is what it always was.

A client that genuinely operates several RUTs (a holding) is served by one organization per RUT, not by several companies inside one tenant. That is also the DT-correct shape: the libro de asistencia is per employer RUT, and the Art. 26 monthly platform upload is a client list keyed by RUT. The tenant switcher (see architecture.md:73) is what makes that usable; it is out of scope here.

**DT compliance constraint — read before touching the report services.** The Art. 27 reports (AttendanceReportService, SundaysReportService, ShiftChangesReportService, DailyReportService) each render an *empresa* column from user->company->social_reason plus rut. That column identifies the **employer of record** and must keep doing so. A cost centre has no RUT and must never be substituted there. Cost centre may be offered as a filter on payroll reports only. Art. 24 (the fiscalizador RUT/name search in Dt\OrganizationController) already reads Organization and is unaffected. Art. 22.3 third-party subcontratacion/EST access is separate access control and is unaffected; is_est stays on the company as a legal-entity fact.

**Recommended shape (decided with the user, 2026-08-05):** keep the companies table and constrain it to one row per organization rather than merging Company into Organization. company_id then stays valid on users, marks, workdays, leaves and premises, and the {{company_*}} contract tokens resolved by DocumentVariableResolver keep working — so the DT report services need no change at all. Full consolidation onto Organization stays available as a later cleanup.

This partially supersedes KOL-30: its companies.code (codigo contable) and its employee company filter both belong on the cost centre now.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 An organization has exactly one company: the one-per-organization rule is enforced at the database level, not only in the controller
- [x] #2 Company create, delete and list screens are gone; the employer profile is edited from a single settings-style page, and every existing route or link to the old CRUD either redirects there or is removed
- [x] #3 A CostCenter model exists as an organization-scoped catalogue with full CRUD, following the Position pattern (model, controller, pages/cost-centers), with a name and an optional codigo contable that is unique within the organization
- [x] #4 Employees reference at most one cost centre, optionally; employees with none assigned keep working everywhere they are read or written
- [x] #5 The employee list can be filtered by cost centre; the company column and company filter added by KOL-30 are removed, since company is now constant within a tenant
- [x] #6 The codigo contable moves off companies onto cost centres, and the migration preserves any existing companies.code value rather than dropping it
- [x] #7 The migration handles an organization that already has more than one company explicitly and without silent data loss; the chosen behaviour is documented in the implementation notes
- [x] #8 The Art. 27 DT reports (attendance, sundays, shift changes, daily) still identify the employer by razon social and RUT; a test asserts the employer column is not the cost centre
- [x] #9 Pest tests cover the one-company-per-organization constraint, cost centre code uniqueness scoped per organization, the employee-with-no-cost-centre path, and the cost centre employee filter
- [x] #10 docs/architecture.md no longer contradicts itself about whether an organization may hold several companies
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
1. Migrations (4): create cost_centers (organization_id, name, code nullable, unique(organization_id, code)); add users.cost_center_id nullable FK nullOnDelete after company_id; data migration converting every extra company per org into a cost centre and moving its employees; add unique index on companies.organization_id.
2. Extra-company handling (AC#7): the oldest company per org is retained as the employer. Each extra becomes a CostCenter carrying its social_reason as name and its code; its employees move to the retained company_id and get the new cost_center_id. The extra company row is soft-deleted with organization_id nulled - the row and every legal field survive for forensics, it falls outside OrganizationScope, and NULL does not collide with the new unique index (MySQL treats NULLs as distinct). No row is destroyed. Verified against prod-shape data: 1 org / 1 company, so this path is defensive.
3. companies.code is dropped; its value migrates onto the cost centre created for that company (or onto a cost centre named after the retained company when it carried a code).
4. CostCenter model + factory + CostCenterPolicy mirroring Position/PositionPolicy. Controller with index/store/update/destroy (no show); destroy blocked when active employees are assigned, matching PositionController.
5. CompanyController becomes a singleton: edit/update with no route parameter, no index/create/store/destroy. Route becomes GET/PUT /company (company.edit, company.update). Handles an org with no company yet by rendering an empty form and creating on first save.
6. Frontend: delete pages/companies/{index,create}.tsx, keep edit.tsx as the single employer profile page; new pages/cost-centers/index.tsx + cost-center-form-dialog.tsx following positions; sidebar entry for cost centres, company entry repointed at company.edit.
7. EmployeeController: replace the company column/filter/options with cost centre; the employee form drops the company select and auto-assigns the org company on create, and gains a cost centre select.
8. Lang es/en for cost_centers.*, company.* rename, validation attribute for cost centre code. Regenerate Wayfinder with --with-form.
9. Tests: new CostCenterManagementTest; rewrite CompanyManagementTest for the singleton; extend EmployeeManagementTest for the cost centre filter; add a DT report assertion that the employer column is razon social + RUT and not the cost centre; a migration test for the multi-company conversion.
10. docs/architecture.md: rewrite the contradictory "Company as the cost-centre dimension" section and line 86.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Shape: companies table kept and constrained to one live row per organization; Company was NOT merged into Organization. company_id therefore stays valid on users/marks/workdays/leaves/premises and the {{company_*}} contract tokens resolve unchanged, so the four Art. 27 DT report services needed no production change at all.

DB enforcement (AC#1) went through two dead ends worth recording:
1. A plain unique index on companies.organization_id cannot work - companies is soft-deleted and a retired row keeps its tenant link, so it collides with the live one.
2. Nulling organization_id on retired rows was the first fix, but companies.organization_id is NOT NULL in the schema (the model docblock says int|null and is wrong about the DB).
Final shape: a generated column companies.live_organization_id = IF(deleted_at IS NULL, organization_id, NULL) carries the unique index. Live rows collide, soft-deleted rows do not (MySQL treats NULLs in a unique index as distinct), and nothing is nulled or destroyed. It must be VIRTUAL, not STORED - adding a STORED generated column derived from a foreign-key column fails with MySQL errno 1215.
Related trap: when a unique index sits directly on organization_id, MySQL folds the FK supporting index into it and the index can no longer be dropped (errno 1553). Not an issue in the final shape, but it is why the first down() was broken.

Multi-company conversion (AC#7): oldest company per org retained as employer; each extra becomes a CostCenter carrying its social_reason and code; its employees move to the retained company_id and the new cost_center_id; its premises move to the retained company; the extra row is soft-deleted with every field intact including organization_id. If the retained company itself carried a code, that code becomes a cost centre too and its employees are assigned to it. Duplicate codes across companies drop the code on the later one rather than aborting the migration. Verified against prod-shape data first: 1 org / 1 company, so this path is defensive only. Irreversible by design - down() cannot know which cost centres were once companies.

Employer assignment: EmployeeController::store now assigns company_id from the org single company instead of offering a select. The employee form lost the company field entirely and gained a cost centre select; employees/show keeps the employer (relabelled ui.employees.form.employer) and adds the cost centre alongside it.

Not touched, flagged as possible follow-up: PremiseController still offers a company select (premise->company_id). With one company per org it is a single-option dropdown - redundant but harmless, and outside this task acceptance criteria.

Verification (2026-08-05): full suite ./vendor/bin/sail artisan test --compact = 700 tests, 696 passed, 4 skipped (pre-existing), 3588 assertions. vendor/bin/pint --dirty --format agent = passed. npm run types:check = clean. sa route:list confirms company.edit/company.update and the four cost-centers routes, with no companies.index/create/store/destroy remaining.

Evidence per acceptance criterion: AC1 CompanyManagementTest "the database refuses a second company for the same organization" + CompanyToCostCenterMigrationTest "the unique index is in place after the migration". AC2 CompanyManagementTest "there is no route to create or delete a company" plus route:list. AC3+AC9 CostCenterManagementTest (14 tests). AC4 EmployeeManagementTest "an employee with no cost centre assigned still lists and loads". AC5 EmployeeManagementTest "employees can be filtered by cost centre" and "the employees list surfaces the cost centre each employee charges to". AC6 CompanyToCostCenterMigrationTest "the retained company accounting code survives as a cost centre" + CompanyManagementTest "the company no longer carries an accounting code". AC7 CompanyToCostCenterMigrationTest (7 tests). AC8 DtReportEmployerIdentityTest (3 tests).

UI evidence is Inertia component/prop assertions in the test runner plus the user own browser review of the company form, cost centre CRUD, employee form/list/detail before merge.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Constrained each organization to a single company and split the centro de costo out into its own CostCenter model. The one-employer rule is enforced in MySQL via a generated column (live_organization_id = IF(deleted_at IS NULL, organization_id, NULL)) carrying the unique index, so soft-deleted employer rows survive intact without colliding. Company CRUD became a singleton form at GET/PUT /company; the codigo contable moved off companies onto cost centres; employees gained an optional cost_center_id and the employee list filters by it. A data migration converts any pre-existing extra company into a cost centre without destroying a row. Art. 27 DT reports still identify the employer by razon social and RUT, guarded by DtReportEmployerIdentityTest. Verified with 700 tests (696 passed, 4 pre-existing skips), clean Pint and clean types:check.
<!-- SECTION:FINAL_SUMMARY:END -->
