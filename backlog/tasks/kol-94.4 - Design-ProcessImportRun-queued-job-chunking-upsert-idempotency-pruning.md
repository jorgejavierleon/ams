---
id: KOL-94.4
title: 'Design ProcessImportRun queued job: chunking, upsert, idempotency, pruning'
status: Done
assignee:
  - '@me'
created_date: '2026-09-02 19:04'
updated_date: '2026-09-03 18:52'
labels:
  - 'wayfinder:grilling'
milestone: m-3
dependencies:
  - KOL-94.1
parent_task_id: KOL-94
type: task
ordinal: 76000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Question

Design the queued commit step, mirroring `app/Jobs/GenerateReportExport.php`'s status-flip + notification + `failed()` hook pattern: how ProcessImportRun chunks through ImportRows (informed by the PhpSpreadsheet research ticket), applies CreateOnly/UpdateOnly/CreateAndUpdate per row via the chosen match key, what happens on a mid-job failure (partial commit vs all-or-nothing), and the completion-notification design (success/partial-success/failure cases). Also design the scheduled pruning of abandoned ImportRuns (expires_at + a command mirroring `report-exports:prune-expired`, deleting stale Pending/MappingReview/PreviewReady runs and their uploaded files).
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Comments

<!-- COMMENTS:BEGIN -->
created: 2026-09-03 18:52
---
ProcessImportRun design resolved via grilling (2026-09-03):

1. Chunking: config('imports.commit_chunk_size', 200), env-backed, single value shared by CSV and Xlsx. Chunk size governs DB-write batching, not read memory — per KOL-94.1, Xlsx always fully loads into memory in one load() regardless of chunk size, so there's no memory-side reason to differentiate by format.

2. Transaction boundary: DB::transaction() wraps each chunk (not the whole ImportRun). Bounds lock duration and blast radius; a crash near the end of a large run only loses the current chunk's progress, not everything.

3. Upsert mechanism: per-row Eloquent, not bulk upsert() — $model = $existingMatch ?? new $targetModelClass; $model->fill($importRow->resolvedData); $model->save(). resolvedData's fill-set genuinely differs per row (KOL-94.3: blanks already omitted), which doesn't fit a bulk upsert() and would bypass casts/model events. Wrapped per-row in try/catch: a DB-level constraint violation (e.g. two rows in the same file racing on a unique RUT) is caught and reclassified as that row's Error (increments the errored count), never thrown out of the chunk.

4. Failure granularity: row-level Errors already exclude just that row and let the run continue — this was already locked by CONTEXT.md's ImportIssue definition ("an Error excludes it"), not re-litigated here. Whole-job failure (the failed() hook) is reserved for exceptions outside per-row handling: uploaded file missing/unreadable, the PhpSpreadsheet reader throwing on load(), or an unrecoverable exception (lost DB connection, OOM) escaping the per-row try/catch. Anything row-scoped and expected (validation, reference resolution, unique-constraint race) is caught and counted, never propagates.

5. Idempotency / resume: ImportRun gets a `committed_through` row-number cursor plus running counts (created/updated/skipped/errored), both updated at the end of each successful chunk transaction. $tries = 3 with backoff on the job — a transient failure (e.g. a dropped DB connection) retries automatically and resumes from committed_through + 1, skipping rows already committed, rather than requiring a human to notice and manually re-dispatch. failed() only fires once tries are exhausted (or on a non-retryable failure per point 4).

6. Error-report file: ProcessImportRun streams each row's issues to a file on the `local` disk during the same re-run of EvaluateImportRow that performs the commit (re-parsing the upload a second time was ruled out — this job is already iterating every row), storing the path on ImportRun. Regenerated from scratch on every attempt, including retries, rather than resumed/appended: both formats already pay the full parse/load cost regardless of chunk size or cursor position (KOL-94.1), so a fresh full pass is cheap and avoids partial-file bookkeeping. Only the DB upsert and cursor/count increment are skipped for rows <= committed_through; issue-writing runs for every row on every attempt. Exact CSV column format is KOL-94.8's scope, not this ticket's.

7. Completion notification: a single ImportRunCompleted notification (mirrors ReportExportReady) is sent for every run that reaches Completed — full success and partial success (some rows errored, run still finished) alike, since CONTEXT.md's ImportRun statuses have no separate "partially completed" state. Payload: counts {created, updated, skipped, errored}, plus a download link for the error report when errored > 0. A separate ImportRunFailed notification (mirrors ReportExportFailed) is sent only from the job's failed() hook.

8. Pruning: expires_at is set once at ImportRun creation (now()->addHours(N), configurable), not refreshed per wizard-step transition — mirrors ReportExport's flat-expiry model rather than a sliding window. A new scheduled command (mirrors report-exports:prune-expired, hourly) deletes every ImportRun whose status is in [Pending, MappingReview, PreviewReady] and whose expires_at has passed, plus its uploaded file. A run stuck in Processing (e.g. a dead queue worker) is explicitly out of scope for this command — detecting that needs a different signal (a staleness heartbeat, not a flat expiry) and risks false positives against legitimately slow large imports; carried forward as unspecified fog rather than decided here.

Out of scope for this ticket: the sync/queue threshold for the preview step (commit is unconditionally queued per the map's locked decision; only preview has a sync path, and its threshold number belongs to a different ticket), and the error-report CSV's exact column format (KOL-94.8).
---
<!-- COMMENTS:END -->
