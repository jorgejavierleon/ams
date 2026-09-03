---
id: KOL-94
title: Bulk data import framework map
status: Done
assignee: []
created_date: '2026-09-02 19:03'
updated_date: '2026-09-03 20:33'
labels:
  - 'wayfinder:map'
milestone: m-3
dependencies: []
type: task
ordinal: 72000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Destination

A spec for a generalized, data-driven bulk import framework (upload → column mapping → preview/validation → strategy & match-key selection → confirmation → queued commit with per-row error reporting), mirroring the ReportExport/GenerateReportExport pattern, plus the concrete Employee import built on it as the first resource — ready to be broken into implementation tickets.

## Notes

- Domain: AMS (Laravel 13 + Inertia v3 + React 19). Skills to consult per ticket: laravel-best-practices, pest-testing, inertia-react-development, wayfinder-development; tailwindcss-development and frontend-design for any UI prototype ticket.
- Terminology is locked in CONTEXT.md ("Bulk Import" section): ImportRun, ImportSchema, ColumnMapping, ImportRow, ImportIssue, Match key, Import strategy, Reference field. Read it before working any ticket.
- Standing precedent to mirror: `app/Models/ReportExport.php` + `app/Jobs/GenerateReportExport.php` (status enum, queued job, completion notification) — docs/architecture.md names this as the pattern to reuse.
- Standing precedent to reuse: `EmployeeMasterExporter::prepare()` (app/Services/Reports/EmployeeMasterExporter.php) for the Employee column set; `EmployeeController::validateEmployee()` for per-row validation rules.
- Code org convention: `app/Actions/*` (discrete per-row ops) + `app/Services/*` (stateless orchestration) + thin controllers.
- Uploaded files go on the `local` (private) disk, never `public`. ImportRun is scoped to the acting user's `organization_id`, like everything else in this app.
- Locked decisions (do not re-litigate): queued (not sync) commit step; ImportSchema is a PHP class per resource; wizard is server-driven (one ImportRun row updated per step, not client-only wizard state); preview runs synchronously for v1 unless a row-count threshold is crossed (mirror `config/reports.php`'s `export.queue_threshold`); template is generated dynamically from the ImportSchema; error report is a plain on-demand download (no signed-URL/notification); abandoned ImportRuns are pruned on a schedule like `report-exports:prune-expired`; new `Import:Employee` Spatie permission (follow RoleSeeder's `View:X`/`Export:X` convention) rather than gating on `role:admin`.

## Decisions so far

- [Research PhpSpreadsheet import capabilities for large files](KOL-94.1): Csv reader streams row-by-row (cheap) but Xlsx reader always fully parses worksheet XML upfront with no true streaming/chunked read — ProcessImportRun should do one full Xlsx load then chunk in application code; app's memory_limit is -1, so the sync/queue preview threshold should be wall-clock-driven with separate CSV/Xlsx numbers rather than reusing config/reports.php's flat 500-row figure; neither reader detects header rows or reliably types CSV dates; upload validation must go through IOFactory::identify()/createReaderForFile() (never a directly-constructed Reader\Csv on a trusted extension). Full findings: docs/research/phpspreadsheet-import-capabilities.md.
- [Define EmployeeImportSchema field list, validation rules, and match-key semantics](KOL-94.2): Field list is full form-parity (export set + supervisor/is_admin/timezone/vacation balances), not just the export columns; reference fields (cost_center/premise/position/contract_type/supervisor) resolve by case-insensitive exact match, unresolved = row Error; RUT/Email match keys compare normalized/lowercased, ID compares exact; blank cell on update means no-change (never clear-to-null); CreateOnly required set mirrors the manual form; uniqueness mirrors the app's existing global (non-org-scoped) Rule::unique. Full detail: ticket's Implementation Notes.
- [Design ImportSchema contract and ColumnMapping/ImportRow value objects](KOL-94.3): ImportSchema is a resource-agnostic interface (fields/rules/resolveReferences/findExisting/targetModel), implemented once per resource; the universal per-row sequence (map → cast → resolve references → find existing → validate → omit blanks → assemble) lives in a shared `EvaluateImportRow` action, not on the schema itself, so reference-error/blank-cell-no-change policy is enforced exactly once. ColumnMapping/ImportField/ImportRow/ImportIssue are readonly value objects (this app's existing VO convention); ColumnMapping persists as a JSON column on ImportRun (mirrors ReportExport::$filters), ImportRow/ImportIssue stay ephemeral, never persisted per-row. Full contract: ticket's Implementation Notes.
- [Design ProcessImportRun queued job: chunking, upsert, idempotency, pruning](KOL-94.4): Per-chunk DB transactions (config-driven chunk size, shared CSV/Xlsx) with per-row Eloquent upsert; a `committed_through` cursor + running counts make retries (`$tries=3`) idempotent and resumable; job-level `failed()` is reserved for exceptions outside per-row handling, never validation/reference/constraint issues; the error-report file is streamed inline during the same commit pass and regenerated from scratch on every attempt; a single `ImportRunCompleted` notification covers both full and partial success; abandoned pre-commit runs (Pending/MappingReview/PreviewReady) prune hourly on a flat `expires_at`, mirroring `report-exports:prune-expired`. Full detail: ticket's resolution comment.
- [Design wizard step endpoints and ImportRun status-transition contract](KOL-94.5): Single `ImportWizardController` with one route per step (store/show/updateMapping/updateStrategy/preview/commit) plus an error-report download, all keyed off ImportRun's own status; an over-threshold file is rejected at upload rather than given a queued-preview sub-path; going back to an earlier step is allowed and demotes status, clearing `preview_counts`; commit flips status to Processing itself before dispatching `ProcessImportRun` to close a double-submit race; Failed is terminal, no retry route. Full route table and guard rules: ticket's Implementation Notes.
- [Decide Import:Employee permission name and default role assignment](KOL-94.6): Single Spatie permission `Import:Employee` (guard `web`) gates the whole wizard including the error-report download, per KOL-94.5's route contract; seeded to `admin` only by default (RoleSeeder::ADMIN_PERMISSIONS) — supervisors hold no Employee-record permissions today and stay excluded by default, though an admin can grant it to another role later via the Roles screen. Full resolution: ticket's resolution comment.
- [Prototype column auto-mapping algorithm and mapping-review UI](KOL-94.7): Flat table with a single confidence threshold (0.6) wins over confidence-tiered and triage-queue alternatives; one inline searchable Combobox per row (plus an explicit Ignore option) is the whole Fix flow, for both Unmapped and already-guessed rows. Confirms ColumnMapping's Mapped/Unmapped/Ignored shape (KOL-94.3) needs no confidence/score field, since the real screen never surfaces one. Accepted open risk: short aliases (CC, TZ) can hit spurious 100% confidence — not solved, flagged for later. Full 3-variant prototype preserved on throwaway branch prototype/kol-94-7-import-mapping (commit 59b4bdc).
- [Decide template generation and error-report CSV format](KOL-94.8): Template generator walks EmployeeImportSchema's own field order and reuses the export's Spanish column labels — no example data row, offered as both Excel and CSV via the existing ReportWriter pattern (adds a `GET imports/employee/template/{format}` route to KOL-94.5's contract). Error-report CSV is one line per ImportIssue (not per ImportRow) with columns Fila/Columna/Severidad/Mensaje, generated by plain fputcsv+BOM during ProcessImportRun's commit pass and served through the same authenticated-disk-download pattern as ReportExport. Full detail: ticket's Implementation Notes.

## Not yet specified

- Whether ImportSchema definitions ever need to be admin-configurable (no-code) rather than a PHP class per resource — revisit once a second resource (Leaves/Premises/Positions) is actually being built.
- How the framework will expose itself for a second resource (schema registry, route naming convention like `imports/{resource}`) — deliberately not pinned since only Employee is being built now.
- Detecting and reaping an ImportRun stuck in Processing (e.g. a dead queue worker) — KOL-94.4's pruning command only covers pre-commit stale runs (Pending/MappingReview/PreviewReady) via a flat `expires_at`; a stuck-Processing run needs a different staleness signal (a heartbeat, not a flat expiry) and risks false positives against legitimately slow large imports, so it wasn't decided as part of that ticket.

## Out of scope

- Rollback/undo of a completed ImportRun.
- Actually building Leaves/Premises/Positions importers.
- Provisioning a production queue worker (flagged as external dependency — docs/architecture.md already notes none is deployed).
- An import-run history/list screen (deferred to a separate future effort).
- Pixel-level UI design (this map specs the flow and data contracts, not visual polish).
- Aligning plain `employees` CRUD route gating (currently `role:admin`, no Spatie permission) with the new `Import:Employee` permission on the same model — surfaced while resolving KOL-94.6, tracked as its own effort: KOL-95 (Align Employee CRUD route gating with Spatie permissions).
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
- [Prototype the full 6-step import wizard UI flow](KOL-94.9): Full working React/Inertia prototype of the wizard sequence — Upload -> Mapping review (KOL-94.7's Variant A, reused as-is) -> Strategy & match key -> Preview & validation -> Confirm & commit -> Result — verified live in-browser (Chrome DevTools MCP), no console errors. Confirms the step sequence and per-step data implied by KOL-94.5's endpoint contract feel right, including that Preview can only ever show aggregate counts (never a per-row grid) and that editing mapping/strategy after PreviewReady correctly demotes the run and re-locks Confirm until preview reruns — verified by walking that path live, not just by reading the code. Full 12-file prototype preserved on throwaway branch prototype/kol-94-9-import-wizard (commit ad54e8a). This was the map's last open ticket.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
All 9 wayfinder tickets resolved — the destination (a spec for a generalized, data-driven bulk import framework, plus the concrete Employee import as its first resource) is reached and ready to be broken into implementation tickets. Route walked: PhpSpreadsheet capabilities research -> EmployeeImportSchema field list/match keys -> ImportSchema/ColumnMapping/ImportRow contract -> ProcessImportRun job design -> wizard endpoint/status contract -> Import:Employee permission -> mapping-algorithm+UI prototype -> template/error-report format -> full 6-step wizard UI prototype. Two follow-on efforts spun off rather than folded in: KOL-95 (Employee CRUD route-gating alignment, out of scope) and the fog items left in Not yet specified (admin-configurable schemas, multi-resource registry, stuck-Processing detection) for whenever a second resource is built.
<!-- SECTION:FINAL_SUMMARY:END -->
