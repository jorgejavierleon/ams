---
id: KOL-104
title: Add scheduled pruning of abandoned Employee import runs
status: To Do
assignee: []
created_date: '2026-09-03 20:45'
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
- [ ] #1 A new scheduled command runs hourly and deletes every ImportRun whose status is Pending, MappingReview, or PreviewReady and whose expires_at has passed, along with its uploaded file
- [ ] #2 Runs in Processing, Completed, or Failed are never touched by this command, regardless of expires_at
- [ ] #3 Feature tests cover: a stale Pending/MappingReview/PreviewReady run and its file are deleted, a stale run in Processing is left untouched, and a non-expired run is left untouched
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
