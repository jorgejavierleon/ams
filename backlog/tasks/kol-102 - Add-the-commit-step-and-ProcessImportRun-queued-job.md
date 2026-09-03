---
id: KOL-102
title: Add the commit step and ProcessImportRun queued job
status: To Do
assignee: []
created_date: '2026-09-03 20:44'
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
- [ ] #1 POST imports/{importRun}/commit requires PreviewReady, flips the ImportRun to Processing itself (before dispatch, not inside the job) to close a double-submit race, and dispatches ProcessImportRun
- [ ] #2 ProcessImportRun processes rows in config('imports.commit_chunk_size', 200) chunks, each wrapped in its own DB::transaction(); each row is upserted via $existingMatch ?? new $targetModel then fill(resolvedData)+save(), per the run's strategy and match key
- [ ] #3 committed_through and the running created/updated/skipped/errored counts update at the end of each successful chunk; the job has $tries = 3 and, on retry, resumes from committed_through + 1 without re-applying already-committed rows
- [ ] #4 A per-row failure that EvaluateImportRow or a DB constraint produces (validation, unresolved reference, unique-constraint race) is caught and counted as that row's Error; it never aborts the chunk or the job
- [ ] #5 The job's failed() hook fires only for an exception outside per-row handling (unreadable/corrupt upload, reader failure, unrecoverable DB/OOM) once retries are exhausted, and moves the ImportRun to Failed
- [ ] #6 An ImportRunCompleted notification is sent once all chunks finish, whether every row succeeded or some errored; ImportRunFailed is sent only from failed()
- [ ] #7 The result screen polls GET imports/{importRun} while Processing and shows the final created/updated/skipped/errored counts on Completed, or the failure state on Failed; Failed offers no retry action
- [ ] #8 Tests cover: a clean fixture creates/updates the expected rows, a fixture with a mid-file unique-constraint collision counts that row as errored and still reaches Completed, a forced non-row-level failure lands the run in Failed with no upserts beyond the last committed chunk, and a retried job resumes from committed_through without double-applying earlier chunks
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
