---
id: KOL-111
title: Show a per-row error table on the Employee import preview step
status: Done
assignee:
  - '@jorge'
created_date: '2026-09-06 11:54'
updated_date: '2026-09-06 19:25'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-101
  - KOL-103
references:
  - >-
    backlog/tasks/kol-101 -
    Add-the-preview-and-validation-step-to-the-Employee-import-wizard.md
  - backlog/tasks/kol-103 - Add-the-Employee-import-error-report-download.md
ordinal: 98000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The preview step (KOL-101) only shows aggregate Ready/Warning/Error/Skipped counts and never the specific rows/fields/messages behind an Error count — the only way to see what is actually wrong with a row today is to commit the import (even a 0-row commit, if every row errors) and then download the post-commit error-report CSV (KOL-103) from the result step. A user staring at "19 de 19 filas quedaran excluidas" has no way to find out which 19 fields are wrong without leaving the wizard, opening a spreadsheet, and cross-referencing row numbers by hand.

This reverses KOL-101 AC #2 ("the preview screen shows only the aggregate counts — never a per-row grid or list"), which was the deliberate original design; the new premise is that fixing errors before committing is common enough that hiding the detail behind a file download is the wrong tradeoff.

Add a paginated table of per-row issues (Fila, Columna, Severidad, Mensaje — same shape as the existing CSV columns from KOL-94.8) directly on the preview step, visible once preview_counts show any error/warning, so a user can see exactly what to fix in their source file without downloading anything or committing the import first. Reuse the existing DataTable/useServerTable foundation (#58) for the table itself. This is additive to, not a replacement for, the post-commit CSV download (KOL-103), which stays as-is for the result step.

## User stories for manual testing (Gherkin)

Scenario: Seeing exactly which rows failed, before committing
  Given I uploaded an employee file where 19 of 19 rows have a validation problem
  And I mapped columns and chose a strategy
  When I run the preview step
  Then I see the aggregate stat tiles (Ready 0, Error 19)
  And I also see a table listing each failing row, its column, and the error message
  And I never had to click "Confirmar e importar" or download a file to see this

Scenario: Fixing the source file using the on-screen detail
  Given the preview step shows a per-row error table
  When I open my source spreadsheet
  Then I can find each failing row by its "Fila" number and fix the field named in "Columna" using the "Mensaje" shown
  And I can re-upload a corrected file and run preview again to confirm the errors are gone

Scenario: A clean file shows no error table
  Given I uploaded a file where every row is Ready
  When I run the preview step
  Then no per-row error table is shown, only the aggregate stat tiles

Scenario: Editing mapping after seeing errors clears the stale list
  Given the preview step is showing a per-row error table
  When I go back and change the column mapping
  And I run preview again
  Then the error table reflects only the new preview run, with no leftover rows from the previous attempt
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Running preview on a run with at least one Error or Warning row persists that row's per-row issues (row number, field label, severity, message) so they can be queried/paginated later, not just the aggregate counts
- [x] #2 The preview step shows a paginated table of per-row issues (columns: Fila, Columna, Severidad, Mensaje) whenever preview_counts.error > 0 or preview_counts.warning > 0; the table is absent when both are 0
- [x] #3 Editing mapping or strategy after PreviewReady (which already demotes the run and clears preview_counts per KOL-101 AC #3) also clears the previously persisted per-row issues, so a stale error list can never be shown against a new preview run
- [x] #4 Re-running preview replaces any previously persisted issues for that run rather than appending to them
- [x] #5 The per-row issues table/endpoint is scoped the same way the rest of the wizard is (same organization + owning user), matching the existing ImportRun access scope from KOL-105
- [x] #6 Feature tests cover: previewing a fixture with a mix of clean/warning/error rows persists the expected issues and the table endpoint returns them paginated in row order; re-running preview replaces rather than appends; editing mapping/strategy after PreviewReady clears the persisted issues; a user outside the run's organization (or without Import:Employee) cannot read another org's issues
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
1. Migration: create import_run_issues (import_run_id FK cascadeOnDelete, row_number unsignedInteger, field nullable string, severity string, message text, timestamps, index [import_run_id, row_number]).
2. Model App\Models\ImportRunIssue (Fillable, casts severity to ImportIssueSeverity, belongsTo ImportRun) + factory.
3. ImportRun model: add issues(): HasMany, ordered by row_number.
4. ImportIssueSeverity enum: add label() -> 'Advertencia'/'Error'.
5. Extract ImportErrorReportWriter::buildLabels() into shared App\Support\Imports\ImportFieldLabels::build(ImportSchema): array; reuse in the writer and the new persistence/read paths; ImportErrorReportWriter::write() uses severity->label().
6. PreviewImportRun::handle(): collect $result->issues per row while evaluating; after the loop, delete existing $importRun->issues() then bulk-insert the new batch before persisting preview_counts (replace not append, AC#4).
7. ImportWizardController::demotionFrom(): when demoting a PreviewReady run, also delete $importRun->issues() (AC#3).
8. ImportWizardController::show(): add ResolvesTablePerPage; when preview_counts has error>0 or warning>0, paginate $importRun->issues()->withQueryString()->through() into {id,row,column,severity,message} (column/severity resolved via ImportFieldLabels + severity->label()); pass as `issues` prop (null otherwise). No new route needed.
9. Frontend: show.tsx adds Issue type + issues prop, passes to PreviewStep; preview-step.tsx renders a DataTable (Fila/Columna/Severidad/Mensaje) gated on previewCounts.error>0 || previewCounts.warning>0, routeUrl=show(importRunId).url, only=['issues'].
10. lang/es+en ui.php: fix run_description copy (no longer true that only totals show) and add preview.issues translation keys.
11. Pest tests in ImportWizardTest.php: persisted issues shape/order after preview; re-preview replaces not appends; demotion (mapping+strategy) clears issues; cross-org user cannot read another org's issues via imports.show.
12. vendor/bin/pint --dirty --format agent; sa test --compact --filter=ImportWizardTest; npm run types:check; full sa test --compact before Phase 4 gate.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented per plan. New import_run_issues table + ImportRunIssue model persist EvaluateImportRow's per-row issues (previously ephemeral) whenever PreviewImportRun runs; replaced (not appended) on every re-preview, and cleared inside demotionFrom() alongside the existing preview_counts clear (KOL-101 AC #3 hook). Extracted ImportErrorReportWriter's buildLabels() into shared App\Support\Imports\ImportFieldLabels so the CSV (KOL-103) and the new on-screen table use the exact same Spanish field-label mapping; added ImportIssueSeverity::label() likewise shared for the Spanish severity word.

Frontend: the paginated issues table is served as an additional `issues` prop on the existing imports.show Inertia response (no new route) — only queried when preview_counts.error>0 or .warning>0 — and rendered in preview-step.tsx via the existing DataTable/useServerTable foundation (#58), reloading with `only: ['issues']` partial visits for pagination.

Verified end-to-end in a real browser (Chrome DevTools MCP, since the claude-in-chrome extension wasn't connected this session): logged in as the seeded admin, ran preview on a 3-row UpdateOnly fixture (1 Ready, 1 Warning/Skipped, 1 unresolved-reference Error), confirmed the Fila/Columna/Severidad/Mensaje table rendered with the correct single Error row and working pagination footer, then resubmitted the strategy step and confirmed both preview_counts and the persisted ImportRunIssue rows cleared (AC #3), then re-ran preview and confirmed the table reappeared correctly. No console errors. Test fixture (ImportRun #17) cleaned up afterward.

Tests: 43/43 ImportWizardTest pass (6 new: mixed clean/warning/error persistence+pagination shape, clean-fixture-shows-no-table, re-preview replaces not appends, mapping/strategy demotion clears issues x2, cross-org 404 on issues). Full suite: 1405 passed, 7 skipped, 0 failed. Pint clean. npm run types:check clean (2 pre-existing unrelated errors in roles/index.tsx and roles/show.tsx, confirmed present on master before this branch). ESLint/Prettier clean on touched files. npm run build succeeds.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a paginated per-row issue table (Fila/Columna/Severidad/Mensaje) to the Employee import preview step, shown whenever preview_counts has an error or warning. Per-row issues from EvaluateImportRow are now persisted (new import_run_issues table) on every preview run, replacing prior issues on re-preview and cleared on mapping/strategy demotion. Verified with 6 new Pest tests (43/43 in ImportWizardTest, full suite 1405 passed/7 skipped/0 failed) and an end-to-end browser walkthrough (Chrome DevTools MCP) confirming the table renders, paginates, and clears correctly.
<!-- SECTION:FINAL_SUMMARY:END -->
