---
id: KOL-111
title: Show a per-row error table on the Employee import preview step
status: To Do
assignee: []
created_date: '2026-09-06 11:54'
updated_date: '2026-09-06 11:54'
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
- [ ] #1 Running preview on a run with at least one Error or Warning row persists that row's per-row issues (row number, field label, severity, message) so they can be queried/paginated later, not just the aggregate counts
- [ ] #2 The preview step shows a paginated table of per-row issues (columns: Fila, Columna, Severidad, Mensaje) whenever preview_counts.error > 0 or preview_counts.warning > 0; the table is absent when both are 0
- [ ] #3 Editing mapping or strategy after PreviewReady (which already demotes the run and clears preview_counts per KOL-101 AC #3) also clears the previously persisted per-row issues, so a stale error list can never be shown against a new preview run
- [ ] #4 Re-running preview replaces any previously persisted issues for that run rather than appending to them
- [ ] #5 The per-row issues table/endpoint is scoped the same way the rest of the wizard is (same organization + owning user), matching the existing ImportRun access scope from KOL-105
- [ ] #6 Feature tests cover: previewing a fixture with a mix of clean/warning/error rows persists the expected issues and the table endpoint returns them paginated in row order; re-running preview replaces rather than appends; editing mapping/strategy after PreviewReady clears the persisted issues; a user outside the run's organization (or without Import:Employee) cannot read another org's issues
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
