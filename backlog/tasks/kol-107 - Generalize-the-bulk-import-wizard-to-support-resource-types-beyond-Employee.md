---
id: KOL-107
title: Generalize the bulk-import wizard to support resource types beyond Employee
status: To Do
assignee: []
created_date: '2026-09-06 10:30'
labels: []
dependencies: []
ordinal: 94000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The import wizard built for Employees (KOL-94/96-106) is hardcoded to EmployeeImportSchema: ImportWizardController, ProcessImportRun, and CreateImportRunFromUpload all type-hint the concrete class directly, and the imports/ routes carry no resource segment. Only ImportSchema-typed collaborators (EvaluateImportRow, PreviewImportRun, ImportErrorReportWriter) are already generic. Before a second importable resource (starting with Shift Assignments) can be added, the wizard needs to resolve its ImportSchema by resource type instead of by concrete class, so adding a new resource requires no controller/job changes.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 ImportWizardController resolves the ImportSchema implementation, permission, and template from a resource-type identifier instead of a concrete class type-hint
- [ ] #2 ProcessImportRun and CreateImportRunFromUpload resolve their ImportSchema the same way
- [ ] #3 Routes carry the resource type (e.g. imports/employees/{importRun}) and 404 for an unregistered resource type
- [ ] #4 A single registry (or equivalent binding) maps each resource-type key to its ImportSchema class, Spatie permission, and template class
- [ ] #5 Existing Employee import behavior and permission gating are unchanged; all existing import tests still pass
- [ ] #6 Adding a future importable resource requires only a new ImportSchema implementation and a registry entry, no changes to ImportWizardController or ProcessImportRun
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
