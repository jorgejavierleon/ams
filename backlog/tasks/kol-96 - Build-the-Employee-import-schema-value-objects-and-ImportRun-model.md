---
id: KOL-96
title: 'Build the Employee import schema, value objects, and ImportRun model'
status: Done
assignee:
  - '@me'
created_date: '2026-09-03 20:43'
updated_date: '2026-09-03 21:47'
labels:
  - bulk-import
milestone: m-3
dependencies: []
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: task
ordinal: 83000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Foundation for the bulk import framework specced in KOL-94: the resource-agnostic ImportSchema contract, its value objects, the concrete EmployeeImportSchema, the shared per-row evaluation action, the ImportRun model, and the Import:Employee permission. Nothing here is user-facing yet — every later wizard-step ticket is built on top of this. Follows app/Actions + app/Services convention; see KOL-94.2/94.3/94.6 for the full locked design.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 ImportSchema interface exists with fields(), rules(strategy, existingMatch), resolveReferences(row), findExisting(matchKey, value), targetModel()
- [x] #2 ImportField, ColumnMapping, ImportRow, ImportIssue are final readonly value objects with the enums from KOL-94.3 (ImportFieldType, MatchKeyComparator, ColumnMappingStatus, ImportRowStatus, ImportIssueSeverity)
- [x] #3 EmployeeImportSchema declares the full field list from KOL-94.2 (the 18-column export set plus supervisor/is_admin/timezone/vacation balances/administrative_days/has_additional_sundays/overtime_rest_day_eligible), marks cost_center/premise/position/contract_type/supervisor as reference fields, and marks RUT/Email/ID as match-key eligible
- [x] #4 App\Actions\Imports\EvaluateImportRow composes map -> cast -> resolveReferences -> findExisting -> rules -> omit-blanks -> assemble, per KOL-94.3's sequence
- [x] #5 An unresolved reference field produces a whole-row Error (never a field-level Warning); a blank cell in a non-match-key column on UpdateOnly/CreateAndUpdate is omitted from resolvedData (no clear-to-null)
- [x] #6 Match-key comparison: RUT normalized via the app's existing Rut normalization, Email lowercased, ID an exact integer match against users.id
- [x] #7 CreateOnly rejects a row missing any of first_name/last_name/email/rut/timezone; ID match key has no effect under CreateOnly
- [x] #8 ImportRun model + migration: status enum (Pending, MappingReview, PreviewReady, Processing, Completed, Failed), organization_id scope, column_mapping (json), strategy, match_key, preview_counts (json), committed_through, created/updated/skipped/errored counts, error_report_path, expires_at
- [x] #9 Import:Employee permission (guard web) exists, seeded to the admin role only
- [x] #10 Pest tests cover EvaluateImportRow for: a clean create, an update with a blank non-match-key cell (no-change), an unresolved reference (row Error), each match key's comparison semantics, and a CreateOnly row missing a required field
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
1. Enums (app/Enums/, flat, matching existing convention): ImportRunStatus, ImportStrategy, ImportFieldType, MatchKeyComparator (with normalize()), ColumnMappingStatus, ImportRowStatus, ImportIssueSeverity.
2. Value objects under app/Support/Imports/ (final readonly, mirrors ReportEmployeeFilters convention): ImportField, ColumnMapping, ImportRow, ImportIssue, ReferenceResolution.
3. app/Services/Imports/ImportSchema.php interface (fields/rules/resolveReferences/findExisting/targetModel) + app/Services/Imports/EmployeeImportSchema.php implementing it per KOL-94.2's locked field list/match-key/reference rules, reusing lang/*/ui.php's employees.form.* labels and validateEmployee()'s rule shapes.
4. app/Actions/Imports/EvaluateImportRow.php composing map -> cast -> resolveReferences -> findExisting -> rules -> omit-blanks -> assemble per KOL-94.3's sequence.
5. app/Models/ImportRun.php + migration (organization-scoped via BelongsToOrganization) with exactly the columns in AC #8, + ImportRunFactory mirroring ReportExportFactory.
6. Import:Employee permission: add to RoleSeeder::ADMIN_PERMISSIONS, add lang/en+es ui.php roles.permissions entries, add missing 'id' field label to ui.employees.form.*.
7. Pest tests (tests/Feature/EvaluateImportRowTest.php, tests/Feature/ImportRunTest.php) covering AC #10's five scenarios plus permission seeding.
8. pint --dirty, sa test --compact filtered to new files, then full suite before final commit.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implementation complete. Code-reviewed via /code-review (forked agent), which surfaced and I fixed 3 real bugs before finalizing:
- A mapped `id` column leaked into resolvedData even when a different match key was active (ImportField now has isIdentifierOnly; EvaluateImportRow's assembly step drops those unconditionally).
- EmployeeImportSchema::rules() lacked the self-supervision guard EmployeeController::validateEmployee() applies (Rule::notIn on supervisor_id).
- EvaluateImportRow didn't respect UpdateOnly's own semantics for an unmatched row (it validated/passed as if creating); now returns ImportRowStatus::Skipped with a Warning issue when strategy=UpdateOnly and no existing match is found.
All three fixes are covered by new regression tests in EvaluateImportRowTest.php.

Verification: vendor/bin/pint --dirty clean; full-project phpstan analyse 0 errors; full suite via sa test --compact: 1350 passed, 4 skipped (pre-existing), 0 failures (user-run).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Built the foundation for the Employee bulk-import framework (KOL-94): App\Services\Imports\ImportSchema interface + EmployeeImportSchema (26-field list per KOL-94.2, reference-field resolution, RUT/Email/ID match keys), the resource-agnostic App\Actions\Imports\EvaluateImportRow per-row pipeline, readonly value objects (ImportField/ColumnMapping/ImportRow/ImportIssue/ReferenceResolution) under App\Support\Imports, 7 new enums, the ImportRun model+migration+factory, and the Import:Employee permission (admin only). Verified with 11 new Pest tests covering every AC #10 scenario plus 3 bugs a /code-review pass caught (id-field leakage into resolvedData, missing self-supervision guard, UpdateOnly not skipping unmatched rows) — all fixed and regression-tested. Full suite: 1350 passed, 4 skipped, 0 failures; pint and phpstan clean.
<!-- SECTION:FINAL_SUMMARY:END -->
