---
id: KOL-94.8
title: Decide template generation and error-report CSV format
status: Done
assignee:
  - '@me'
created_date: '2026-09-02 19:04'
updated_date: '2026-09-03 20:06'
labels:
  - 'wayfinder:grilling'
milestone: m-3
dependencies:
  - KOL-94.2
parent_task_id: KOL-94
type: task
ordinal: 80000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Question

Decide the concrete format for the dynamically-generated import template (columns, order, any example row) sourced from EmployeeImportSchema, and the exact error-report CSV format for Step 4 (columns: row, error, column per the original mockup — confirm or adjust), plus how it's served (plain authenticated download, per the locked "no signed-URL/notification" decision).
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Template + error-report format resolved via grilling (2026-09-03):

## Template generation

1. Format: both Excel and CSV, mirroring EmployeeMasterExporter's existing `FORMATS = ['excel', 'csv']` / `employees/export/{format}` precedent exactly — same route shape (`GET imports/employee/template/{format}`, 404 on an unlisted format), same ReportWriter (excel()/csv() from one rendered HTML fragment) so the template needs no new file-generation code path. Both are needed since KOL-94.1 confirmed both formats are accepted on upload; forcing users into one direction would break the CSV-in/Excel-out (or vice versa) symmetry.

2. Columns/order: exactly EmployeeImportSchema's field order (single source of truth — the generator walks the schema's field list, nothing hand-maintained separately). Per KOL-94.2: the 18 import-eligible export columns in their existing order (first_name .. is_active, company excluded) followed by the 8 import-only fields (supervisor, is_admin, timezone, vacation_days, additional_vacation_days, administrative_days, has_additional_sundays, overtime_rest_day_eligible).

3. Headers: human Spanish labels, not raw field keys — reuse `ui.employees.export.columns.*` verbatim for the 18 overlapping fields (same strings the on-screen export already uses); add 8 new `ui.employees.import.columns.*` keys for the import-only fields (exact Spanish wording is an implementation-time detail, not decided here). This label set becomes the single source both the template header row and the error-report's "column" value (below) read from, and is what KOL-94.7's auto-mapper should hit at high confidence when a user re-uploads the unmodified template.

4. No example/sample data row. A filled example row risks being left in place and re-uploaded as a bogus real row (CreateOnly would try to create a fake employee from it); the mapping-review screen (KOL-94.7) already carries the burden of explaining expected formats per column. Headers-only keeps the generator trivial and removes that failure mode. Not revisited unless user testing on KOL-94.9's prototype shows real confusion.

5. Filename: `Str::slug(__('ui.employees.import.template.title'))` (e.g. "plantilla-importacion-empleados"), same pattern as EmployeeMasterExporter's `Str::slug(__('ui.employees.export.title'))`.

6. Route addendum to KOL-94.5's contract: `GET imports/employee/template/{format}` on ImportWizardController (a `template()` method alongside store/show/updateMapping/...), gated by the same auth + Import:Employee permission as the rest of the wizard (KOL-94.6). This route was missing from KOL-94.5's table — noted as a comment there rather than reopening it, since it's additive, not a change to what was already decided.

## Error-report CSV

7. Grain: one CSV row per ImportIssue, not per ImportRow — a row with two invalid fields produces two CSV lines. Keeps every line single-valued (no multi-issue cell to parse/read), and lets a user sort/filter by column or severity in Excel.

8. Columns (confirms row+column, adjusts "error" -> two columns, adds severity): `Fila` (row), `Columna` (column), `Severidad` (severity), `Mensaje` (error message) — in that order (scan order: locate the row, locate the field, judge urgency, read what's wrong). Spanish headers, matching every other export in this app (EmployeeMasterExporter forces `es` regardless of interface locale).
   - `Fila`: ImportRow::rowNumber, defined as the 1-indexed row number in the uploaded file including the header row (so the first data row is row 2) -- what the user sees if they open their own file in Excel. This pins down ImportRow::rowNumber's indexing convention, left open by KOL-94.3.
   - `Columna`: the ImportSchema field's human label (same `ui.employees.export.columns.*` / `ui.employees.import.columns.*` set from decision #3), not the literal header text from the user's uploaded file -- stable and readable regardless of what the user named their column. Blank when ImportIssue::field is null (a whole-row issue, e.g. an unresolved reference per KOL-94.2/94.3).
   - `Severidad`: "Advertencia" or "Error" (ImportIssue::severity). Included even though the route is only offered once `errored > 0` (KOL-94.5) -- once the report exists, it lists every issue from the run, not just the Error-severity ones, so a user can also see which rows imported with a warning.
   - `Mensaje`: ImportIssue::message as-is.

9. Generation: plain `fputcsv` streamed to the local disk during ProcessImportRun's commit pass (per KOL-94.4, not re-derived here) -- NOT routed through ReportWriter::csv(), which builds a full HTML table via PhpSpreadsheet and isn't suited to incremental per-chunk writes. UTF-8 with a BOM (same reason as ReportWriter::csv(): Excel under a Chilean locale must not mangle acentos/eñes), comma delimiter (no user-facing delimiter choice exists for this file, unlike the configurable master export).

10. Storage/serving: disk path `"import-runs/{organization_id}/{importRun->id}-errores.csv"` on the `local` disk (mirrors GenerateReportExport's `"report-exports/{organization_id}/{id}-{filename}"` convention), path stored on ImportRun per KOL-94.4. Served by `ImportWizardController::downloadErrorReport()` (already named in KOL-94.5) via `Storage::disk('local')->download(...)`, exactly matching `DownloadReportExport::handle()`'s pattern (auth + org-scope, no signed URL) -- confirms the map's locked "plain authenticated download" decision needs no new mechanism, just the existing one.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Template: both Excel and CSV via the existing ReportWriter/EmployeeMasterExporter pattern (route `GET imports/employee/template/{format}`, addendum to KOL-94.5), headers-only (no example row) in EmployeeImportSchema field order with human Spanish labels reused by the error report. Error-report CSV: one line per ImportIssue with columns Fila/Columna/Severidad/Mensaje (adjusts the original row/error/column mockup by splitting the message from severity), generated via plain fputcsv+BOM during ProcessImportRun's commit pass and served through the existing DownloadReportExport-style authenticated disk download. Full detail in Implementation Notes.
<!-- SECTION:FINAL_SUMMARY:END -->
