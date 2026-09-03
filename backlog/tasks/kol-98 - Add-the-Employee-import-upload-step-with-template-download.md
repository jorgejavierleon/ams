---
id: KOL-98
title: Add the Employee import upload step with template download
status: Done
assignee:
  - '@jorge'
created_date: '2026-09-03 20:44'
updated_date: '2026-09-03 22:57'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-97
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: feature
ordinal: 85000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
First user-facing slice of the Employee import wizard specced in KOL-94: the ImportWizardController is introduced with its template-download and upload (store) routes, and the wizard shell renders the first step. Depends on KOL-97's ImportRun model and permission. Full route contract: KOL-94.5 (addendum in KOL-94.8 for the template route); threshold rationale: KOL-94.1.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET imports/employee/template/{format} (excel|csv), gated by Import:Employee, downloads a template built from EmployeeImportSchema's field order with human Spanish labels and no example data row; an unlisted format 404s
- [x] #2 POST imports/employee accepts an uploaded file, validates its real format via IOFactory::identify()/createReaderForFile() (not the file extension), and rejects anything else
- [x] #3 A valid upload creates an ImportRun scoped to the acting user's organization_id, sets expires_at from a config-driven TTL, and transitions Pending -> MappingReview
- [x] #4 An upload whose row count exceeds the sync-preview threshold (separate configurable CSV/Xlsx numbers per KOL-94.1) is rejected immediately with a validation error, never queued or partially previewed
- [x] #5 GET imports/{importRun} (show) renders per the ImportRun's current status; unauthorized users and users outside the ImportRun's organization get a 403/404
- [x] #6 Feature tests cover: a valid upload reaches MappingReview, an over-threshold file is rejected, a renamed-extension file is rejected, a user without Import:Employee gets 403, and the template downloads with the expected header row and order
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Migration: add nullable `disk_path` and `original_filename` columns to import_runs (needed for later steps per KOL-94.4's pruning; ImportRun model docblock/Fillable already lacked a place to store the uploaded file).
2. config/imports.php: sync_preview_threshold.{csv,excel} (separate numeric defaults per KOL-94.1) + expiry_hours (drives ImportRun::expires_at).
3. App\Actions\Imports\CreateImportRunFromUpload: given an UploadedFile + ImportSchema, resolve the real reader via IOFactory::createReaderForFile($path, ['Xlsx','Csv']) (rejects anything else per AC#2), load it, count data rows vs config threshold (AC#4, reject before creating any ImportRun row), then create the ImportRun (org-scoped via BelongsToOrganization, expires_at from config), store the file to local disk at import-runs/{org}/{id}.{ext}, parse the header row into an initial column_mapping skeleton (all Unmapped — ColumnAutoMapper is KOL-99's scope), and transition Pending -> MappingReview.
4. App\Http\Controllers\ImportWizardController: create() (renders the wizard shell's upload step, no ImportRun yet — mirrors every other resource's create/store split in this app), template({format}) (EmployeeImportSchema field order minus isIdentifierOnly fields, human labels already on ImportField, reuses ReportWriter + a new headers-only Blade fragment), store() (delegates to the Action, redirects to show), show(ImportRun $importRun) (renders per status — only MappingReview is reachable yet, since store() always finishes synchronously; 403 via permission middleware, 404 for cross-org via ImportRun's existing OrganizationScope on route-model binding).
5. Routes: imports/employee/create, imports/employee/template/{format}, POST imports/employee, GET imports/{importRun}, all behind auth+verified+permission:Import:Employee (mirrors the payroll-reports permission-gated group).
6. Frontend: resources/js/pages/imports/employee/create.tsx (template download links + drag/drop upload form, posts via useForm+forceFormData) and show.tsx (status-driven wizard shell; today only renders a MappingReview summary placeholder — the interactive mapping table is KOL-99). Add an "Importar" entry point button on employees/index.tsx gated by auth.permissions.includes('Import:Employee'). Translations under ui.employees.import.* (es + en). Regenerate Wayfinder (--with-form).
7. Pest feature tests (ImportWizardTest or similar): valid upload reaches MappingReview; over-threshold file rejected without creating a run; renamed-extension file rejected; user without Import:Employee gets 403; template download has expected header row/order (18+8 fields, id excluded).
8. pint --dirty, sa test --compact (filtered), npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verification:
- Pest: tests/Feature/ImportWizardTest.php (7 tests) covers AC #1-6 — valid upload reaches MappingReview with org-scoped ImportRun/expires_at/column_mapping skeleton, over-threshold rejected before any ImportRun is created, a renamed-extension (non-spreadsheet content) file is rejected, a user without Import:Employee gets 403 on both create and store, a cross-org ImportRun 404s via the existing OrganizationScope on route-model binding, and the template download's header row/order matches EmployeeImportSchema's fields (minus isIdentifierOnly) in Spanish.
- Full suite: sa test --compact -> 1357 passed, 4 skipped (pre-existing), 0 failed.
- vendor/bin/pint --dirty and phpstan analyse (full app) both clean.
- npm run types:check: no new errors introduced by this ticket. Two pre-existing failures remain (resources/js/pages/roles/index.tsx, roles/show.tsx — a role id string/number Wayfinder mismatch unrelated to imports); confirmed present on master via git stash before this work started. Leaving DoD #3 unchecked since the command itself doesn't fully pass, though nothing here regressed it.
- Manually verified in-browser (localhost): template download, drag/drop + browse upload, permission-gated "Importar" button on /employees. Found and fixed a stale dev-DB gap where RoleSeeder hadn't been re-run since Import:Employee was added (KOL-96) — seeded it so the admin user has the permission.

Scope additions beyond KOL-94.5's literal route table (documented as reasonable inferences, not re-litigated with the user mid-task):
- Added GET imports/employee/create (+ ImportWizardController::create()) as the wizard's actual entry point/upload-step page — KOL-94.5's table only lists store/show/template, mirroring every other resource's create+store split already used throughout this app.
- Added disk_path + original_filename columns to import_runs via a new migration (KOL-96/97's original migration had nowhere to persist the uploaded file, which later steps/pruning (KOL-94.4) need).
- config/imports.php: sync_preview_threshold.{csv,excel} numeric defaults (5000/2000) and expiry_hours (24) are implementation-time defaults per KOL-94.1, not previously locked numbers.
- show()'s MappingReview branch is a placeholder summary only (column count + filename) — the interactive mapping table is KOL-99's scope, not built here.

Applied code-review fixes (5 findings from /code-review): (1) added a cheap upfront CSV line-count pre-check so an oversized CSV is rejected before PhpSpreadsheet builds a Cell object per value — directly closes the DoS-shaped resource-exhaustion scenario the review flagged; Xlsx is unchanged since no cheaper alternative exists per KOL-94.1's research. (2) wrapped the disk write in try/catch that deletes the just-created ImportRun row on failure, so a storage error never leaves an orphaned Pending run with no file. (3) removed the unused ImportSchema $schema parameter from CreateImportRunFromUpload::handle() (and the controller's now-unneeded EmployeeImportSchema injection). (5) extracted the duplicated filenameFrom() content-disposition parser (also present in employees/index.tsx) into resources/js/lib/download.ts, used by both. Left (4) — the pre-existing missing response.ok check on template/export blob downloads — as-is: it's an established pattern already used across the app's export buttons, not something this ticket introduced, and fixing it here would mean silently changing unrelated code the user didn't ask about.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Implemented the Employee import wizard's upload step (KOL-98): ImportWizardController with create/template/store/show, gated by permission:Import:Employee. store() resolves the upload's real format via IOFactory::createReaderForFile (restricted to Xlsx/Csv, never trusting the extension), rejects anything else or anything over the per-format sync-preview row threshold (config/imports.php) before creating any ImportRun, then persists the file, parses the header row into an initial Unmapped ColumnMapping, and transitions Pending -> MappingReview. template() downloads a headers-only Excel/CSV file built from EmployeeImportSchema's field order. Frontend: a create.tsx upload page (template links + drag/drop, wired via useForm) and a status-driven show.tsx (MappingReview placeholder today), plus an "Importar" entry point on the Employees index gated by the same permission. Verified via tests/Feature/ImportWizardTest.php (7 tests, all AC covered) and manual in-browser testing; full suite (1357 passed), Pint, and PHPStan all clean. See Implementation Notes for the handful of reasonable scope additions (create() route, disk_path/original_filename columns, config defaults) made to deliver a working slice within KOL-94.5's locked contract.
<!-- SECTION:FINAL_SUMMARY:END -->
