---
id: KOL-99
title: Add the column mapping review step to the Employee import wizard
status: Done
assignee:
  - '@jorge'
created_date: '2026-09-03 20:44'
updated_date: '2026-09-04 01:36'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-98
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: feature
ordinal: 86000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Second wizard step: auto-map uploaded columns against EmployeeImportSchema and let the user fix/confirm them. Algorithm and UI already validated in KOL-94.7's throwaway prototype (Variant A) — reuse that shape rather than re-deriving it. Depends on KOL-98's upload step existing.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A ColumnAutoMapper scores each uploaded header against EmployeeImportSchema's fields and marks it Mapped at score >= 0.6, otherwise Unmapped; no confidence score is persisted or surfaced in the UI
- [x] #2 The mapping-review screen is a flat table (one row per uploaded column) with an inline searchable Combobox listing every schema field plus an explicit 'Ignore this column' option, used for both Unmapped rows and fixing an already-Mapped guess
- [x] #3 The wizard refuses to proceed past mapping while any of EmployeeImportSchema's CreateOnly-required fields is Unmapped
- [x] #4 Feature tests cover: auto-mapping a fixture header set (including a few intentionally ambiguous/short headers) produces the expected Mapped/Unmapped/Ignored split, saving a mapping with all required fields mapped succeeds, and saving with a required field still Unmapped is rejected
- [x] #5 PATCH imports/{importRun}/mapping persists the ColumnMapping array on the ImportRun; allowed while status is MappingReview or PreviewReady (resubmitting while PreviewReady demotes the run per KOL-101)
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
1. App\Actions\Imports\ColumnAutoMapper: schema-agnostic (App\Support\Imports\ImportField), normalized token-overlap scoring (ported from KOL-94.7's Variant A sketch, minus the prototype's aliases field since real ImportField has none), single 0.6 threshold, conflict resolution (highest scorer wins a field, loser stays Unmapped). Returns plain column_mapping arrays (same shape CreateImportRunFromUpload already builds), no VO round-trip needed since nothing downstream consumes ColumnMapping VOs yet.
2. Wire it into App\Actions\Imports\CreateImportRunFromUpload: inject ColumnAutoMapper + EmployeeImportSchema, replace columnMappingSkeleton()'s all-Unmapped build with $autoMapper->map($header, $schema->fields()) so the mapping-review screen has real guesses on first load. Update ImportWizardTest's existing upload assertion (header 'Nombre' now exact-matches first_name's label and comes back Mapped, not Unmapped).
3. ImportWizardController::updateMapping(): PATCH imports/{importRun}/mapping. 409 unless status is MappingReview/PreviewReady (PreviewReady demotion itself is KOL-101's job, not built here). Validates mapping shape, rejects unknown targetField names, rejects two rows mapped to the same targetField (EvaluateImportRow::mapRow silently last-write-wins otherwise), rejects if any EmployeeImportSchema::requiredForCreateOnly field isn't Mapped. On success, persists column_mapping and redirects back().
4. Route: PATCH imports/{importRun}/mapping -> imports.mapping.update, inside the existing Import:Employee-gated group. Regenerate Wayfinder.
5. ImportWizardController::show(): pass full column_mapping plus a serialized schema field list (name/label/requiredForCreateOnly) instead of the placeholder column_count.
6. Frontend: resources/js/pages/imports/employee/mapping-review-step.tsx (adapted from the prototype's Variant A: flat Table + Combobox with an Ignore option, required-missing Alert gating the submit button), wired into show.tsx's MappingReview branch via useForm + router-style patch to imports.mapping.update. Replace the now-unused "coming soon" show.mapping_review_title/description translations with the real UI's strings (es+en).
7. Tests: extend tests/Feature/ImportWizardTest.php — auto-mapping split on upload (exact-label headers -> Mapped, one deliberately unrelated/short header -> Unmapped), PATCH success (all required mapped, one column explicitly Ignored), PATCH rejected when a required field is left Unmapped, PATCH rejected on duplicate targetField.
8. pint --dirty, sa test --compact filtered to Import*, npm run types:check, then full suite.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
App\Actions\Imports\ColumnAutoMapper: normalized (accent-stripped, lowercased) token-overlap scoring against each ImportField's label and name, single 0.6 threshold (no separate floor tier like the prototype — real ImportField carries no aliases, so one threshold is enough), conflict resolution keeps only the single highest scorer per target field. Wired into CreateImportRunFromUpload (replacing KOL-98's all-Unmapped skeleton) so the mapping-review screen has real guesses on first load; updated ImportWizardTest's pre-existing upload assertion to match ('Nombre' now auto-maps to first_name instead of staying Unmapped).

ImportWizardController::updateMapping() (PATCH imports/{importRun}/mapping): 409 outside MappingReview/PreviewReady (PreviewReady demotion-on-resubmit is KOL-101's job — preview doesn't exist yet so this status is never actually reached today). Validates mapping shape, rejects an unknown targetField name, rejects two columns mapped to the same field (EvaluateImportRow::mapRow silently last-write-wins otherwise — a real data-corruption risk worth guarding even though not spelled out in the AC), and rejects any EmployeeImportSchema::requiredForCreateOnly field left Unmapped (strategy isn't chosen until KOL-100, so every required field must always be mapped regardless of which strategy gets picked later).

Frontend: resources/js/pages/imports/employee/mapping-review-step.tsx adapts KOL-94.7's Variant A prototype almost directly (flat Table + existing Combobox component, same Ignore-option UX) wired to real Inertia useForm/patch. show.tsx now passes the full column_mapping and a serialized schemaFields list instead of the old placeholder column_count.

Verification: tests/Feature/ImportWizardTest.php extended with 6 new tests (auto-mapping split via upload, PATCH success incl. an Ignored column, PATCH rejected on missing-required, PATCH rejected on duplicate targetField, PATCH 409 outside MappingReview/PreviewReady) — all passing, 12/12 in that file. Full suite: 1366 tests, 1362 passed, 4 skipped (pre-existing), 0 failures. Pint and full-app PHPStan both clean.

Manually verified in-browser (Chrome DevTools MCP, localhost): uploaded the real Employee template CSV -> 26/26 columns auto-mapped correctly; Combobox search-and-select and the Ignore option both work; clearing a required field's mapping correctly re-shows the "required fields missing" alert and disables Guardar mapeo; saving a valid mapping persists column_mapping via the PATCH endpoint with no console errors.

npm run types:check: no new errors from this ticket. Same two pre-existing failures KOL-98 already documented (resources/js/pages/roles/index.tsx, roles/show.tsx — unrelated Wayfinder id-type mismatch). DoD #3 left unchecked for the same reason KOL-98 left it unchecked: the command itself doesn't fully pass, though nothing here regressed it.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Implemented the mapping-review step (KOL-99): App\Actions\Imports\ColumnAutoMapper scores uploaded headers against EmployeeImportSchema's fields (normalized token-overlap, 0.6 threshold, no confidence surfaced), wired into CreateImportRunFromUpload so new uploads get real Mapped/Unmapped guesses. New PATCH imports/{importRun}/mapping (ImportWizardController::updateMapping) persists the reviewed ColumnMapping array, rejecting unknown/duplicate target fields and any CreateOnly-required field left Unmapped, 409 outside MappingReview/PreviewReady. Frontend: mapping-review-step.tsx (flat table + searchable Combobox with an Ignore option, adapted from KOL-94.7's Variant A prototype) wired into show.tsx via Inertia useForm. Verified with 6 new/updated Pest tests in ImportWizardTest.php (all passing), full suite (1366 tests, 1362 passed, 4 pre-existing skips, 0 failures), Pint clean, full-app PHPStan clean, and manual in-browser testing (real template upload auto-mapped 26/26 columns; search/select/ignore, required-field gating, and PATCH persistence all verified with no console errors).
<!-- SECTION:FINAL_SUMMARY:END -->
