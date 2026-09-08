---
id: KOL-107
title: Generalize the bulk-import wizard to support resource types beyond Employee
status: In Review
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-06 10:30'
updated_date: '2026-09-08 10:15'
labels:
  - bulk-import
milestone: m-3
dependencies: []
ordinal: 94000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The import wizard built for Employees (KOL-94/96-106) is hardcoded to EmployeeImportSchema: ImportWizardController, ProcessImportRun, and CreateImportRunFromUpload all type-hint the concrete class directly, and the imports/ routes carry no resource segment. Only ImportSchema-typed collaborators (EvaluateImportRow, PreviewImportRun, ImportErrorReportWriter) are already generic. Before a second importable resource (starting with Shift Assignments) can be added, the wizard needs to resolve its ImportSchema by resource type instead of by concrete class, so adding a new resource requires no controller/job changes.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 ImportWizardController resolves the ImportSchema implementation, permission, and template from a resource-type identifier instead of a concrete class type-hint
- [x] #2 ProcessImportRun and CreateImportRunFromUpload resolve their ImportSchema the same way
- [x] #3 Routes carry the resource type (e.g. imports/employees/{importRun}) and 404 for an unregistered resource type
- [x] #4 A single registry (or equivalent binding) maps each resource-type key to its ImportSchema class, Spatie permission, and template class
- [x] #5 Existing Employee import behavior and permission gating are unchanged; all existing import tests still pass
- [x] #6 Adding a future importable resource requires only a new ImportSchema implementation and a registry entry, no changes to ImportWizardController or ProcessImportRun
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
1. Migration: add non-nullable `resource_type` string column to import_runs (forward-only, no down()).
2. ImportRun model: add resource_type to Fillable + docblock; ImportRunFactory: default resource_type => 'employees' (keeps every existing factory-based test passing unmodified).
3. ImportSchema interface: add newModel(ImportRun): Model, beforeSave(Model): void, afterSave(Model, bool $wasCreated): void — lets ProcessImportRun stop hardcoding User/role-assignment logic.
4. EmployeeImportSchema: implement the 3 new methods, moving newEmployee()/name-derivation/assignRole('employee') out of ProcessImportRun unchanged in behavior.
5. New ImportTemplate interface (formats(): array, download(string): Response); EmployeeImportTemplate implements it.
6. New ImportResourceDefinition value object (schema/permission/template class-strings + schema()/template() resolvers) and ImportResourceRegistry (static resourceType => definition map, find()/findOrFail() 404ing via NotFoundHttpException). Registers 'employees' => EmployeeImportSchema/Import:Employee/EmployeeImportTemplate.
7. New EnsureImportPermission middleware: resolves {resourceType} route param via the registry (404 if unknown), then checks the resolved permission (403 if lacking) — replaces the static `permission:Import:Employee` middleware string.
8. Routes: prefix becomes imports/{resourceType}/..., using EnsureImportPermission; route names simplify to imports.create/template/store/show/mapping.update/strategy.update/preview.store/commit.store/error-report/destroy.
9. ImportWizardController: drop concrete EmployeeImportSchema/EmployeeImportTemplate type-hints; resolve the definition/schema per-request from $resourceType via the registry; verify {importRun}-scoped routes' resource_type matches the URL segment (404 on mismatch); derive the Inertia page path (imports/{resourceType}/...), i18n keys (ui.{resourceType}.import.*), and the post-cancel redirect ({resourceType}.index) generically from $resourceType instead of hardcoding "employee(s)".
10. CreateImportRunFromUpload: accept $resourceType, resolve schema via the registry, persist resource_type on the created ImportRun, use resourceType-driven i18n error keys.
11. ProcessImportRun: resolve the schema from $importRun->resource_type via the registry inside handle() (drop the concrete type-hint entirely so no future resource needs a job change); route save()/applyRow() through newModel/beforeSave/afterSave instead of the Employee-specific newEmployee()/assignRole().
12. Frontend: rename resources/js/pages/imports/employee/ -> imports/employees/ to match the resourceType-driven Inertia page path; thread a resourceType prop from show.tsx/create.tsx down through each step component so every Wayfinder imports/* route call includes it; regenerate Wayfinder (php artisan wayfinder:generate --with-form).
13. Tests: update ImportWizardTest.php's route() calls for the new route shape via a small importRoute() test helper; add coverage for the registry's 404 on an unregistered resource type and for the unchanged Import:Employee permission gating. ProcessImportRunTest/EvaluateImportRowTest/ImportRunTest/PruneAbandonedImportRunsTest need no changes (factory default covers them).
14. vendor/bin/pint --dirty --format agent, filtered `sa test --compact` on import-related tests during development, npm run types:check, then a full sa test --compact pass at the end per DoD.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implementation: added ImportResourceRegistry/ImportResourceDefinition (resourceType -> schema/permission/template class-strings), ImportTemplate interface, EnsureImportPermission middleware (replaces static permission:Import:Employee), and 3 new ImportSchema hooks (newModel/beforeSave/afterSave) so ProcessImportRun no longer hardcodes User construction/role assignment. Routes moved to imports/{resourceType}/... ; ImportRun gained a resource_type column (migration, no down()) set at upload time and used by the queued job (which has no request context) to resolve its schema. Controller/job/action now resolve everything from $resourceType via the registry with zero per-resource branching, satisfying AC #6. Frontend: renamed resources/js/pages/imports/employee -> imports/employees to match the resourceType-driven Inertia page path, threaded a resourceType prop through show.tsx and every step component, regenerated Wayfinder (--with-form).

Verification: vendor/bin/pint --dirty clean (via sail php). Added 2 new Pest tests (unregistered resource type 404s before permission check; importRun/resourceType mismatch 404s). Existing ImportWizardTest/ProcessImportRunTest/EvaluateImportRowTest/ImportRunTest/PruneAbandonedImportRunsTest (77 tests) pass with only route-call syntax updated via a small importRoute() test helper — no assertion changed, proving AC #5 (Employee behavior/permission gating unchanged). Full suite: 1418 passed, 4 pre-existing skips, 0 failures, 6315 assertions. npm run types:check: 0 errors in touched files (2 pre-existing unrelated errors in untouched roles/index.tsx and roles/show.tsx).

Post-review fixes: migration's resource_type column now defaults to 'employees' (backfills any pre-existing row safely — every run before KOL-107 was necessarily an Employee import); ImportResourceDefinition gained an explicit indexRoute field so destroy()'s post-cancel redirect no longer assumes an undeclared '{resourceType}.index' naming convention; centralized the resourceType/importRun ownership check into a single Route::bind('importRun', ...) in routes/web.php instead of 6 near-identical manual controller calls (verified this doesn't change 404-vs-403 precedence: SubstituteBindings already ran before import.permission for the original implicit binding too). Documented (not code-enforced, matching existing bare-string Inertia/lang conventions elsewhere in the app) that a registry key must match its Inertia page directory and lang/*/ui.php top-level key. Re-verified: pint clean, full sa test --compact (1418 passed/4 skipped/0 failed), npm run types:check (same 2 pre-existing unrelated errors in untouched roles/index.tsx and roles/show.tsx).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Generalized the bulk-import wizard beyond Employee (KOL-107): a new ImportResourceRegistry maps a resourceType URL key to its ImportSchema class, Spatie permission, and ImportTemplate class; ImportWizardController, CreateImportRunFromUpload, and ProcessImportRun all resolve through it instead of type-hinting EmployeeImportSchema/EmployeeImportTemplate directly. Routes are now imports/{resourceType}/... (404 via a new EnsureImportPermission middleware for an unregistered type), ImportRun carries its own resource_type so the queued job can resolve its schema without request context, and ImportSchema gained newModel/beforeSave/afterSave hooks so Employee-specific User construction and role assignment live in EmployeeImportSchema, not the job. Verified via the full existing import test suite (unchanged assertions, only route-call syntax updated) plus 2 new tests for the 404 cases, vendor/bin/pint, npm run types:check, and a full sa test --compact run (1418 passed, 0 failed).
<!-- SECTION:FINAL_SUMMARY:END -->
