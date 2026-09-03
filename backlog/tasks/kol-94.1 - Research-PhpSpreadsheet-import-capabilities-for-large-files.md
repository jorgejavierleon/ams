---
id: KOL-94.1
title: Research PhpSpreadsheet import capabilities for large files
status: Done
assignee:
  - '@me'
created_date: '2026-09-02 19:03'
updated_date: '2026-09-03 15:06'
labels:
  - 'wayfinder:research'
milestone: m-3
dependencies: []
parent_task_id: KOL-94
type: task
ordinal: 73000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Question

Investigate `phpoffice/phpspreadsheet` (^5.9, already installed)'s `Reader\Xlsx` and `Reader\Csv` classes as the basis for parsing uploaded Employee import files. Answer:

- Can rows be read incrementally/streamed (e.g. a read-filter or chunked reading) without loading the whole spreadsheet into memory, and what's the practical row-count/memory ceiling for a synchronous request (PHP's default memory_limit in this app)?
- How is the header row detected/handled, and how are CSV delimiter/encoding (UTF-8 vs Latin-1) and BOM handled by `Reader\Csv`?
- How are cell types coerced (e.g. a date cell vs a date-as-string, numeric-looking strings, empty cells) — what does the app need to normalize itself vs. what the reader gives for free?
- Does the library distinguish "this is genuinely an .xlsx" vs "this is a .csv with a misleading extension" reliably, for upload validation?

This feeds two other tickets: the sync-vs-queued preview threshold (mirrors `config/reports.php`'s `export.queue_threshold`) and the ProcessImportRun chunking design. Report findings as a short reference doc/comment, not code changes.
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Findings committed to master at docs/research/phpspreadsheet-import-capabilities.md (commit f40e5d4), researched via a background agent in an isolated worktree against the app's actual vendored phpoffice/phpspreadsheet 5.9.0 source (composer.lock-confirmed) plus the official PHPSpreadsheet GitHub docs/samples, with an empirical throwaway-script check (not committed) of IOFactory format detection. No PHP/TS files were changed, so pint/tests/types-check DoD items don't apply beyond 'nothing to break'.
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
created: 2026-09-02 19:15
---
Research complete. Full findings with source citations at docs/research/phpspreadsheet-import-capabilities.md (merged to master, commit f40e5d4). Summary: Csv reader streams row-by-row (cheap); Xlsx reader always fully parses worksheet XML upfront regardless of read filters (expensive) — no true chunked/streaming read exists for Xlsx in 5.9.0, so ProcessImportRun should do a single full Xlsx load then chunk in application code, not rely on PhpSpreadsheet's IReadFilter loop. App's memory_limit=-1, so the sync/queue preview threshold should be wall-clock-driven and likely needs separate CSV/Xlsx numbers rather than reusing config/reports.php's single 500-row figure. Neither reader detects header rows; CSV auto-detects delimiter but defaults to UTF-8 unless GUESS_ENCODING is explicitly requested (BOM is always auto-handled). Xlsx date detection needs cell styles (broken by setReadDataOnly(true)); CSV never carries date typing — recommend the column-mapping UI declare date columns explicitly for both formats. Numeric-looking CSV strings are auto-cast to int/float by default. IOFactory::identify()/createReaderForFile() reliably content-sniffs real format regardless of extension and should be the only path used for upload validation; a directly-constructed Reader\Csv on a trusted .csv extension will silently misparse a renamed .xlsx.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Researched phpoffice/phpspreadsheet 5.9.0's Reader\Xlsx/Reader\Csv against the vendored source + official docs/samples, with an empirical format-detection check. Findings: (1) Csv streams row-by-row via fgetcsv() (except non-UTF-8 input, which buffers the whole file to re-encode); Xlsx always fully parses worksheet XML into a DOM before any IReadFilter is applied — no true streaming, and the official chunk-read recipe re-parses the whole file per chunk. App's memory_limit is -1 (confirmed via tinker), so the sync/queue threshold should be wall-clock- not memory-driven, and likely needs separate CSV vs Xlsx numbers. (2) Neither reader has a header-row concept — 100% app responsibility. Csv auto-detects delimiter by statistical sampling; always BOM-sniffs and auto-corrects encoding on BOM, but silently assumes UTF-8 for non-BOM files unless GUESS_ENCODING is explicitly requested. (3) Xlsx date-vs-number depends on cell style (Date::isDateTime()), which setReadDataOnly(true) breaks by skipping style parsing; CSV numeric-looking strings are auto-cast to int/float by default (except leading-zero strings); empty cells come back as null via toArray()'s nullValue param, indistinguishable from missing unless CSV's preserveNullString is set. (4) IOFactory::identify()/createReaderForFile() reliably content-sniff format in both rename directions (Xlsx::canRead() does real ZIP/OOXML validation) and must be used for upload validation — directly constructing Reader\Csv on a trusted .csv extension is unsafe, since it will silently misparse a renamed .xlsx with no error.
<!-- SECTION:FINAL_SUMMARY:END -->
