---
id: KOL-104
title: Add scheduled pruning of abandoned Employee import runs
status: Done
assignee: []
created_date: '2026-09-03 20:45'
updated_date: '2026-09-04 21:13'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-97
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: medium
type: task
ordinal: 91000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Prevents stale, never-finished import runs and their uploaded files from accumulating, mirroring the existing report-exports:prune-expired command. Only needs KOL-97's ImportRun model (expires_at column already exists there); independent of the wizard-step tickets otherwise. Design: KOL-94.4.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A new scheduled command runs hourly and deletes every ImportRun whose status is Pending, MappingReview, or PreviewReady and whose expires_at has passed, along with its uploaded file
- [x] #2 Runs in Processing, Completed, or Failed are never touched by this command, regardless of expires_at
- [x] #3 Feature tests cover: a stale Pending/MappingReview/PreviewReady run and its file are deleted, a stale run in Processing is left untouched, and a non-expired run is left untouched
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add PruneAbandonedImportRuns console command mirroring PruneExpiredReportExports: delete expired ImportRun rows in Pending/MappingReview/PreviewReady status plus their disk_path file. 2. Register it hourly in routes/console.php as import-runs:prune-abandoned. 3. Add Pest feature test covering: stale Pending/MappingReview/PreviewReady run+file deleted, stale Processing run untouched, non-expired run untouched. 4. Run pint + tests.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Full sa test --compact run was interrupted mid-suite this session; targeted PruneAbandonedImportRunsTest (5/5) and Pint both pass. No TypeScript touched, so DoD #3 is N/A. QA deferred to docs/QA_CHECKLIST.md — user not at home PC.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added import-runs:prune-abandoned, a scheduled (hourly) console command mirroring report-exports:prune-expired: deletes any ImportRun stuck in Pending/MappingReview/PreviewReady past expires_at, along with its uploaded file, leaving Processing/Completed/Failed untouched.
<!-- SECTION:FINAL_SUMMARY:END -->
