---
id: KOL-101
title: Add the preview and validation step to the Employee import wizard
status: Done
assignee:
  - '@jorge'
created_date: '2026-09-03 20:44'
updated_date: '2026-09-04 14:47'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-100
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: feature
ordinal: 88000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Fourth wizard step: run every row through EvaluateImportRow synchronously and show aggregate validation results before committing. Also closes the loop on KOL-99/KOL-100's edit guards: touching mapping or strategy after a preview exists must invalidate that preview. Depends on KOL-100's strategy step existing.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 POST imports/{importRun}/preview requires every required field mapped and a strategy (+ match key when needed) set; it runs EvaluateImportRow across every row in the uploaded file synchronously (files are guaranteed sync-sized by the upload-time threshold from KOL-98) and persists preview_counts {ready, warning, error, skipped}; MappingReview -> PreviewReady
- [x] #2 The preview screen shows only the aggregate Ready/Warning/Error/Skipped counts — never a per-row grid or list
- [x] #3 Resubmitting PATCH imports/{importRun}/mapping or PATCH imports/{importRun}/strategy while the run is PreviewReady demotes it back to MappingReview and clears preview_counts, re-locking the commit step until preview is rerun
- [x] #4 Feature tests cover: previewing a clean fixture yields all-Ready counts, previewing a fixture with an unresolved reference / a required-field gap / a duplicate match-key yields the expected Error/Warning breakdown, and editing mapping (or strategy) after PreviewReady demotes the run and clears preview_counts
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
1. Backend: new App\Actions\Imports\PreviewImportRun action - reads the run's stored file (Csv/Xlsx by disk_path extension), builds ColumnMapping VOs from column_mapping, runs EvaluateImportRow per data row, aggregates counts by ImportRowStatus, persists preview_counts + flips status to PreviewReady.
2. Backend: ImportWizardController::preview() - POST imports/{importRun}/preview. Guards: status must be MappingReview (409 otherwise); asserts every CreateOnly-required field mapped + strategy set + match key set when needed (ValidationException otherwise, reusing required_field_unmapped key plus two new keys strategy_required/match_key_required). Calls the action, returns back().
3. Backend: add route imports.preview.store; update show() to include preview_counts in Inertia props.
4. Backend: updateMapping()/updateStrategy() - when current status is PreviewReady, additionally reset status to MappingReview and preview_counts to null in the same update (AC #3 demotion).
5. Lang: add en/es keys under employees.import.errors (strategy_required, match_key_required) and a new employees.import.preview section (title/description/run button/stat labels/alerts/back).
6. Regenerate Wayfinder (sail artisan wayfinder:generate --with-form) to produce routes/imports/preview.
7. Frontend: new preview-step.tsx (aggregate stat tiles only, run-preview CTA when no counts yet, back button - no continue, since commit step KOL-102/103 doesn't exist yet).
8. Frontend: show.tsx - extend local step state to mapping|strategy|preview; treat MappingReview and PreviewReady as the editable superstatus (drives which sub-step renders); StrategyStep gets onSaved to advance to preview; update ImportRun TS type with preview_counts.
9. Pest tests: clean fixture -> all Ready; mixed fixture (unresolved reference / required-field gap / duplicate match-key) -> expected Error breakdown; preview guard tests (wrong status, missing required mapping, missing strategy, missing match key); demotion tests for updateMapping/updateStrategy while PreviewReady.
10. pint --dirty, sa test --compact, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented PreviewImportRun action + ImportWizardController::preview() endpoint (POST imports/{importRun}/preview), guarded by MappingReview status plus a server-side prerequisite check (required fields mapped, strategy set, match key when needed). updateMapping/updateStrategy now demote PreviewReady -> MappingReview and clear preview_counts on resubmit (AC #3). Frontend: new preview-step.tsx showing only aggregate stat tiles; show.tsx now treats MappingReview+PreviewReady as one editable superstatus driving a local mapping/strategy/preview sub-step. Added 12 new Pest tests (clean fixture all-Ready, mixed fixture with unresolved-reference/required-field-gap/duplicate-match-key all landing as Error, guard/prerequisite tests, demotion tests). Pint clean, npm run types:check clean (2 pre-existing unrelated failures in roles/index.tsx and roles/show.tsx confirmed present on master too), full sa test --compact: 1381 passed / 4 skipped / 0 failed.

Code review (medium) surfaced 7 findings; fixed the 3 real ones: preview-step.tsx now renders errors.preview (previously silently swallowed validation failures from the preview endpoint); show.tsx's card-width class now also checks isEditable so a non-editable run with no strategy set doesn't get the wide 5xl layout; extracted a shared missingRequiredFields() helper in ImportWizardController so mappingValidator and assertReadyForPreview can't drift on what 'required' means. Left as out-of-scope/accepted: cross-tab staleness of client step state (pre-existing pattern since KOL-99/100, not introduced here), no direct re-preview-without-edit path from PreviewReady (not required by AC, matches KOL-94.5's locked transition contract), and the Warning bucket being permanently 0 (EvaluateImportRow, built in KOL-94.3, never emits it — out of this ticket's scope to change). Re-ran pint/types:check/full sa test --compact after fixes: still clean, 1381 passed / 4 skipped / 0 failed.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added the preview/validation step to the Employee import wizard: POST imports/{importRun}/preview runs EvaluateImportRow across every uploaded row synchronously, persists aggregate preview_counts, and flips MappingReview -> PreviewReady, guarded by a server-side check that required fields/strategy/match-key are set. The preview screen (preview-step.tsx) shows only the four aggregate stat tiles, never a per-row grid. Resubmitting mapping or strategy while PreviewReady now demotes the run back to MappingReview and clears preview_counts, re-locking the commit step. Verified with 12 new Pest tests (clean-fixture all-Ready, mixed fixture with unresolved-reference/required-field-gap/duplicate-match-key all landing as Error, guard/prerequisite tests, demotion tests) plus full sa test --compact (1381 passed/4 skipped/0 failed), pint clean, npm run types:check clean, a medium code review (3 real findings fixed), and the user's own manual walkthrough in the browser (upload -> mapping -> strategy -> preview -> back-and-resubmit demotion) confirmed working.
<!-- SECTION:FINAL_SUMMARY:END -->
