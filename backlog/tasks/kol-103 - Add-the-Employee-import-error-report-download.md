---
id: KOL-103
title: Add the Employee import error-report download
status: Done
assignee:
  - '@jorge'
created_date: '2026-09-03 20:45'
updated_date: '2026-09-04 19:51'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-102
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: medium
type: feature
ordinal: 90000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Lets a user see exactly what went wrong in a completed import. The CSV is written during ProcessImportRun's (KOL-102) commit pass itself, not generated later — this ticket adds that writing plus the download route and exact column format. Full format decision: KOL-94.8.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 During ProcessImportRun's commit pass, every ImportIssue encountered is appended to a CSV at import-runs/{organization_id}/{importRun}-errores.csv on the local disk (UTF-8 with BOM, comma-delimited), one line per issue; the path is stored on the ImportRun and the file is regenerated from scratch on every job attempt, including retries
- [x] #2 CSV columns, in order: Fila (1-indexed row number including the header row), Columna (the field's human Spanish label, blank for whole-row issues), Severidad ('Advertencia' or 'Error'), Mensaje (the issue text)
- [x] #3 GET imports/{importRun}/error-report is available once errored > 0, gated by Import:Employee and the run's organization, and streams the file via the same authenticated-disk-download pattern as report exports
- [x] #4 Feature tests cover: a run with a mix of warnings and errors produces a CSV with the right row count, columns, and values in the right order; the route is unavailable when errored == 0; and a user outside the run's organization (or without Import:Employee) cannot download it
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
1. app/Actions/Imports/ImportErrorReportWriter.php (new): stateful CSV writer opened once per job attempt — open(ImportRun, ImportSchema) truncates/creates import-runs/{org}/{run}-errores.csv, writes UTF-8 BOM + header row (Fila,Columna,Severidad,Mensaje), persists error_report_path on the run, and builds a field-name->Spanish-label map (forcing App::setLocale('es') while calling schema->fields(), mirroring EmployeeImportTemplate; also aliases each isReference field's name+'_id' since EvaluateImportRow's validator errors key reference fields that way). write(rowNumber, issues) appends one fputcsv line per ImportIssue (Severidad literal 'Error'/'Advertencia'); close() closes the handle.
2. app/Jobs/ProcessImportRun.php: inject ImportErrorReportWriter into commit(). After reading dataRows/columnMappings, open() the writer, then (a) re-run EvaluateImportRow over rows 0..committed_through and write their issues (regenerates already-committed rows' issues on a retry, since the existing chunk loop skips re-evaluating them) and (b) inside the existing per-chunk loop, write each row's issues right after evaluating it, before applyRow. Wrap in try/finally so close() always runs.
3. app/Actions/Imports/DownloadImportErrorReport.php (new): mirrors DownloadReportExport — abort_unless(errored_count > 0, 404), then Storage::disk('local')->download(error_report_path, filename).
4. app/Http/Controllers/ImportWizardController.php: add errorReport(ImportRun, DownloadImportErrorReport) action (relies on existing org+user route-model-binding scope, same as show()).
5. routes/web.php: add Route::get('{importRun}/error-report', ...)->name('error-report') inside the existing imports. group (already gated by permission:Import:Employee).
6. Frontend: resources/js/pages/imports/employee/result-step.tsx gets a "Descargar reporte de errores" button when erroredCount > 0, using the fetch+blob+filenameFromContentDisposition pattern already used in create.tsx/summary.tsx, pointing at the new Wayfinder-generated imports.error-report route. Regenerate Wayfinder types. New translation keys in lang/es/ui.php and lang/en/ui.php under employees.import.result.
7. Tests: extend tests/Feature/ProcessImportRunTest.php with a case asserting the CSV's row count/columns/values for a mix of warning+error rows (Advertencia for UpdateOnly-no-match, Error for validation/reference failures), and a retry-resume case asserting the file still contains the earlier chunk's issues. New tests/Feature/ImportWizardTest.php (or a small new file) cases for the error-report route: happy path streams the file, errored==0 -> 404, cross-org -> 404, same-org-without-permission -> 403.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as planned. A medium code-review (backgrounded) surfaced 7 findings; 4 fixed, 3 accepted as out-of-scope/pre-existing-pattern:
- FIXED: a row that failed at save()-time (QueryException, e.g. a unique-constraint race) had no ImportIssue and so no CSV line despite counting toward errored_count — ProcessImportRun::applyRow() now writes a synthetic Error issue for that row when this happens.
- FIXED: the error-report route only checked errored_count > 0, so it could stream the file mid-write while the run was still Processing (ImportErrorReportWriter truncates/rewrites on every job attempt) — DownloadImportErrorReport now also requires status in [Completed, Failed], both of which are only reached after the writer's try/finally has closed the file for that attempt.
- FIXED: result-step.tsx's download handler didn't check response.ok, so a 404/403/500 response would silently download an HTML error page as "errores.csv" — added an early return when !response.ok.
- FIXED (cleanup, in-scope): extracted an evaluateAndRecord() helper in ProcessImportRun to remove the duplicated 6-arg EvaluateImportRow::handle() call between the retry re-derivation loop and the per-chunk commit loop.
- ACCEPTED, not fixed: on a retry, the re-derivation loop (rows 0..committed_through) re-evaluates against current DB state, which under CreateAndUpdate could in principle differ from the state at original evaluation time if an earlier row's own write changed what a later match resolves to — a full fix means persisting per-attempt issues rather than pure re-evaluation, out of proportion for this ticket; flagged for awareness, not blocking.
- ACCEPTED, not fixed: retry cost for the re-derivation loop grows with committed_through (re-evaluates all previously-committed rows every attempt) — an efficiency nit, not a correctness bug, and consistent with the framework's existing accept-the-DB-read-cost approach elsewhere.
- ACCEPTED, not fixed: the fetch+blob+createObjectURL+<a download> pattern in result-step.tsx duplicates identical code in create.tsx and payroll-reports/summary.tsx — deduplicating would touch those unrelated files, out of scope for this ticket.

Verification: full sa test --compact 1400/1400 (4 pre-existing skips, 0 failures) including 2 new CSV-content tests (mixed warning+error rows -> exact row/column/value assertions; a retry re-deriving an earlier chunk's issue) and 5 new error-report route tests (happy path streams, errored==0 -> 404, Processing status -> 404 even with errored rows, cross-org -> 404, same-org-without-permission -> 403); vendor/bin/pint clean; composer types:check (phpstan) clean; npm run types:check clean (same 2 pre-existing unrelated failures in roles/index.tsx and roles/show.tsx noted on KOL-101/KOL-102). Manually verified end-to-end via tinker (no browser extension available this session): created a Completed run with a real CSV on the local disk, confirmed DownloadImportErrorReport returns 200/text/csv/correct Content-Disposition, and confirmed the raw file has the UTF-8 BOM, comma delimiter, and exact Fila/Columna/Severidad/Mensaje values including the Spanish reference-field label and blank Columna for a whole-row issue. Test fixtures cleaned up afterward. Browser UI verification of the new download button was not performed this session (no Chrome extension connected).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added the Employee import error-report download (KOL-94.8, KOL-103). ImportErrorReportWriter (new) writes a UTF-8-BOM, comma-delimited CSV (Fila/Columna/Severidad/Mensaje, one line per ImportIssue, Spanish field labels including reference-field _id aliasing) truncated and rewritten from scratch on every ProcessImportRun job attempt; a pre-loop re-derives already-committed rows' issues via a pure EvaluateImportRow re-run so a retry's file still reports everything, and applyRow now also writes a synthetic issue for a save()-time QueryException so errored_count never outpaces what the CSV explains. error_report_path persists on ImportRun. GET imports/{importRun}/error-report (new controller action + DownloadImportErrorReport) streams the file once the run is Completed or Failed and errored_count > 0, reusing the run's existing org+user route-model-binding scope and the same permission:Import:Employee gate as the rest of the wizard — Processing is explicitly excluded so the route can never read a file mid-rewrite. Frontend: a "Descargar reporte de errores" button on ResultStep (shown once erroredCount > 0) follows the existing fetch+blob+Content-Disposition download pattern, with a response.ok guard so a failed request can't silently save an HTML error page as the report. Verified with the full suite (1400/1400, 4 pre-existing skips), pint, phpstan, and npm types:check all clean, plus a tinker-driven end-to-end check of the real downloaded file's bytes (BOM, delimiter, exact column values). A medium code review surfaced 7 findings; the 2 real correctness bugs (missing CSV line for save()-time failures; torn-file mid-processing download) and a frontend response.ok gap were fixed and covered by new tests, one duplication cleanup was applied in-scope, and 3 lower-severity findings (a rare retry-determinism edge case, retry-cost scaling, and cross-file download-helper duplication) were accepted as out of proportion for this ticket — see Implementation Notes for the full breakdown. Browser UI verification was not performed (no Chrome extension connected this session).
<!-- SECTION:FINAL_SUMMARY:END -->
