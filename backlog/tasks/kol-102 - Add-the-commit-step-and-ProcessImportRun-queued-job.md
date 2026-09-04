---
id: KOL-102
title: Add the commit step and ProcessImportRun queued job
status: Done
assignee: []
created_date: '2026-09-03 20:44'
updated_date: '2026-09-04 15:38'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-101
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: feature
ordinal: 89000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Fifth wizard step and the framework's core write path: committing dispatches a chunked, idempotent queued job that actually creates/updates Employee records. Mirrors GenerateReportExport's status-flip + notification + failed() pattern. Depends on KOL-101's preview step existing. Full design: KOL-94.4 (job mechanics), KOL-94.5 (commit route).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 POST imports/{importRun}/commit requires PreviewReady, flips the ImportRun to Processing itself (before dispatch, not inside the job) to close a double-submit race, and dispatches ProcessImportRun
- [x] #2 ProcessImportRun processes rows in config('imports.commit_chunk_size', 200) chunks, each wrapped in its own DB::transaction(); each row is upserted via $existingMatch ?? new $targetModel then fill(resolvedData)+save(), per the run's strategy and match key
- [x] #3 committed_through and the running created/updated/skipped/errored counts update at the end of each successful chunk; the job has $tries = 3 and, on retry, resumes from committed_through + 1 without re-applying already-committed rows
- [x] #4 A per-row failure that EvaluateImportRow or a DB constraint produces (validation, unresolved reference, unique-constraint race) is caught and counted as that row's Error; it never aborts the chunk or the job
- [x] #5 The job's failed() hook fires only for an exception outside per-row handling (unreadable/corrupt upload, reader failure, unrecoverable DB/OOM) once retries are exhausted, and moves the ImportRun to Failed
- [x] #6 An ImportRunCompleted notification is sent once all chunks finish, whether every row succeeded or some errored; ImportRunFailed is sent only from failed()
- [x] #7 The result screen polls GET imports/{importRun} while Processing and shows the final created/updated/skipped/errored counts on Completed, or the failure state on Failed; Failed offers no retry action
- [x] #8 Tests cover: a clean fixture creates/updates the expected rows, a fixture with a mid-file unique-constraint collision counts that row as errored and still reaches Completed, a forced non-row-level failure lands the run in Failed with no upserts beyond the last committed chunk, and a retried job resumes from committed_through without double-applying earlier chunks
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
1. Add commit() route/action to ImportWizardController — atomic PreviewReady->Processing UPDATE, then dispatch ProcessImportRun.
2. Add ProcessImportRun queued job: chunked commit pass reusing EvaluateImportRow, shared ReadImportFileRows/BuildColumnMappings actions (extracted from PreviewImportRun to avoid drift), committed_through + counts persisted per chunk for idempotent retries, employee create/update via schema targetModel + Employee-specific stamping (org/company/password/role), QueryException caught per-row (not around role assignment), failed() hook + ImportRunCompleted/ImportRunFailed notifications.
3. Add CurrentOrganization::runAs() override so the job's org-scoped lookups (reference resolution, match-key, uniqueness) work with no HTTP session/auth in a real queue worker.
4. Frontend: PreviewStep gets a Confirm & Commit button; new ResultStep component (Processing spinner + usePoll, Completed counts, Failed message, no retry) wired into show.tsx.
5. Translations (es/en) for confirm + result screens and the two new mail notifications.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented commit route/controller action, ProcessImportRun queued job, ImportRunCompleted/ImportRunFailed notifications, and the frontend Confirm/Result steps. Extracted ReadImportFileRows and BuildColumnMappings actions out of PreviewImportRun so the preview and commit passes never read the file or interpret column_mapping differently (also used by the new job). Added CurrentOrganization::runAs() so the job's schema-driven reference/match-key/uniqueness lookups resolve the run's own tenant with no HTTP session or authenticated user, which a real queue worker never has (verified with a dedicated test that calls the job with no actingAs()/session() at all). Verification: full sa test --compact 1393/1393 (4 pre-existing skips) passing, including 5 new ProcessImportRun tests (clean create, update with blank-cell-no-change + cross-org reference scoping, mid-file unique-constraint race caught as row Error via a real interleaved-write test, non-row-level failure -> failed()/notification, and retry-resume from committed_through) and 3 new controller tests (atomic commit dispatch, 409 outside PreviewReady, cross-user 404). vendor/bin/pint clean, composer types:check (phpstan) clean, npm run types:check clean (same 2 pre-existing unrelated failures in roles/index.tsx and roles/show.tsx as noted on KOL-101). Code review (medium, backgrounded) surfaced 3 findings, all fixed: commit() now does an atomic conditional UPDATE (whereKey+where(status=PreviewReady)) instead of fetch-then-write, closing the double-submit race its own docblock claimed to close; ProcessImportRun's per-row QueryException catch no longer wraps assignRole() (a role-assignment failure after a successful save() now propagates as a real job failure instead of being miscounted as that row's Error); the duplicated columnMappings() logic between PreviewImportRun and the new job is now the shared BuildColumnMappings action. Not yet done: browser verification of the new Confirm & Commit / processing-poll / result screens — no dev server exercised this session.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added the commit step: ImportWizardController::commit() atomically flips PreviewReady->Processing (conditional UPDATE, closes the double-submit race) and dispatches the new ProcessImportRun queued job. The job reruns every row through EvaluateImportRow in config('imports.commit_chunk_size')-row chunks, each its own DB::transaction(); a Ready row is upserted (existing match via schema targetModel, or a new employee stamped with org/company/random password/employee role), committed_through and the created/updated/skipped/errored counts persist at the end of each successful chunk so a retry (=3) resumes without redoing prior chunks. A per-row DB constraint failure (e.g. a unique-key race) is caught and counted as that row's error without aborting the chunk/job; only a real job-level failure (unreadable file, DB/OOM, or a role-assignment failure after a successful save) reaches failed(), which moves the run to Failed and sends ImportRunFailed — ImportRunCompleted is sent once all chunks finish regardless of row errors. Added CurrentOrganization::runAs() so the job's schema-driven reference/match-key/uniqueness lookups resolve the run's own tenant with no HTTP session or authenticated user, since a real queue worker has neither. Extracted ReadImportFileRows and BuildColumnMappings out of PreviewImportRun so preview and commit never parse the file or interpret column_mapping differently. Frontend: PreviewStep gets a Confirm & Commit action; a new ResultStep polls (usePoll) while Processing and shows final counts on Completed or a no-retry failure message on Failed. Verified: full sa test --compact 1393/1393 (4 pre-existing skips, 0 failures), including 5 new ProcessImportRun tests (clean create, CreateAndUpdate with blank-cell-no-change and cross-org reference scoping, a real interleaved-write unique-constraint race, a forced non-row failure -> failed()/notification, and retry-resume from committed_through) and 3 new controller tests (atomic commit + dispatch, 409 outside PreviewReady, cross-user 404); vendor/bin/pint clean; composer types:check (phpstan) clean; npm run types:check clean (2 pre-existing unrelated failures in roles/index.tsx and roles/show.tsx, same baseline as KOL-101). A medium code review surfaced 3 findings (commit-race atomicity, assignRole miscounted as a row error, duplicated column-mapping logic) — all fixed and re-verified. Browser verification of the new screens was not performed this session.
<!-- SECTION:FINAL_SUMMARY:END -->
