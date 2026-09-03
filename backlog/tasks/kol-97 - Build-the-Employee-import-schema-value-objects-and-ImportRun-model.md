---
id: KOL-97
title: 'Build the Employee import schema, value objects, and ImportRun model'
status: Done
assignee: []
created_date: '2026-09-03 20:43'
updated_date: '2026-09-03 22:14'
labels:
  - bulk-import
milestone: m-3
dependencies: []
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: task
ordinal: 84000
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

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Duplicate of KOL-96 (identical description and AC list). KOL-96 already implemented and merged this exact scope in commit 255fc10 (ImportSchema contract, value objects, EmployeeImportSchema, EvaluateImportRow, ImportRun model+migration, Import:Employee permission, Pest tests). No new code written for this ticket.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Closed as duplicate of KOL-96, which delivered the identical scope (see commit 255fc10). All acceptance criteria are already satisfied by that implementation; nothing further to build here.
<!-- SECTION:FINAL_SUMMARY:END -->
