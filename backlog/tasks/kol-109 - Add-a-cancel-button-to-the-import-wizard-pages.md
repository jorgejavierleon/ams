---
id: KOL-109
title: Add a cancel button to the import wizard pages
status: To Do
assignee: []
created_date: '2026-09-06 10:37'
updated_date: '2026-09-06 10:37'
labels: []
dependencies: []
ordinal: 96000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
There is currently no user-initiated way to abandon an in-progress ImportRun — only the scheduled PruneAbandonedImportRuns command (KOL-104) cleans up abandoned runs left in Pending, MappingReview, or PreviewReady past their expiry. A user who starts an import and changes their mind (wrong file, wrong resource, wants to restart) has to wait for the prune job or navigate away and leave orphaned state. Add an explicit Cancel action to the wizard steps (mapping-review, strategy, preview) that deletes the ImportRun and its uploaded file immediately, reusing the same disk_path cleanup PruneAbandonedImportRuns already does, and a Back/Cancel link on the initial upload step.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A Cancel button is present on the mapping-review, strategy, and preview wizard steps
- [ ] #2 Cancelling deletes the ImportRun's uploaded file from disk (same disk_path cleanup as PruneAbandonedImportRuns) and deletes the ImportRun row
- [ ] #3 Cancelling is blocked once the ImportRun has moved to Processing or Completed (committing is in flight or already done)
- [ ] #4 After cancelling, the user is redirected to the resource's list page (e.g. Employees) with a confirmation message
- [ ] #5 The initial upload step has a Back/Cancel link back to the resource's list page (no ImportRun exists yet at that step)
- [ ] #6 Cancelling only affects the current user's own ImportRun (respects the KOL-105 ownership scoping)
- [ ] #7 Feature tests cover: cancel deletes the run and file, cancel is rejected once Processing/Completed, and a user cannot cancel another user's run
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
