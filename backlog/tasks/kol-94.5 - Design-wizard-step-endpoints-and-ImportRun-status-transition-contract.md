---
id: KOL-94.5
title: Design wizard step endpoints and ImportRun status-transition contract
status: Done
assignee:
  - '@me'
created_date: '2026-09-02 19:04'
updated_date: '2026-09-03 20:06'
labels:
  - 'wayfinder:grilling'
milestone: m-3
dependencies: []
parent_task_id: KOL-94
type: task
ordinal: 77000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Question

Design the server-driven wizard's HTTP contract: one route/request-response pair per step (upload → confirm mapping → confirm strategy & match key → preview/confirm → start import → status poll), each reading/updating a single ImportRun row per the locked "server-driven, one ImportRun row updated per step" decision. Define the exact ImportRun status enum transitions (Pending→MappingReview→PreviewReady→Processing→Completed|Failed) and what each endpoint is allowed to do to that status.
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
Wizard endpoint contract resolved via grilling (2026-09-03):

1. Route shape: a single ImportWizardController with one method per step, not split into per-step controllers or collapsed into one show+update pair.

2. Routes:
   - POST imports/employee (store): creates ImportRun, uploads file, enforces the sync-preview row/wall-clock threshold from KOL-94.1 at upload time (over-threshold files are rejected immediately with a validation error, never queued or partially previewed); Pending -> MappingReview once the header row is parsed.
   - GET imports/{importRun} (show): renders whatever step the current status implies; also the route Inertia polls (usePoll) while Processing -- no separate JSON status endpoint.
   - PATCH imports/{importRun}/mapping (updateMapping): persists ColumnMapping. Allowed while MappingReview or PreviewReady; if PreviewReady, resubmitting demotes back to MappingReview and clears preview_counts (backward nav is allowed and resets downstream state).
   - PATCH imports/{importRun}/strategy (updateStrategy): persists strategy + match key. Same MappingReview/PreviewReady-demote guard as mapping.
   - POST imports/{importRun}/preview (preview): requires mapping fully resolved (no Unmapped required fields) and strategy+match key set; runs EvaluateImportRow once across the whole file (guaranteed sync-sized by the upload-time threshold check) and persists only preview_counts {ready, warning, error, skipped} as a JSON column on ImportRun -- matches KOL-94.3's "only aggregate counts land on ImportRun," so the wizard never surfaces a persisted per-row table; MappingReview -> PreviewReady.
   - POST imports/{importRun}/commit (commit): requires PreviewReady; flips status to Processing itself before dispatching ProcessImportRun (not inside the job's handle(), unlike GenerateReportExport's pattern) -- closes the double-submit race where a second click could dispatch the job twice before the queue worker claims the first attempt; PreviewReady -> Processing.
   - GET imports/{importRun}/error-report (downloadErrorReport): streams the CSV KOL-94.8 defines, available once errored > 0.

3. Guard behavior: any request against a transition the current status doesn't allow (including the two PATCHes once status is Processing/Completed/Failed) gets a 403 and an Inertia redirect-back with a flash error -- consistent with how this app already surfaces authorization failures, rather than a 422 'invalid transition' shape.

4. Failed is terminal from the wizard's perspective: no retry endpoint. A job-level failure (the failed() hook) means the user re-uploads via imports/employee and starts over; matches the ReportExport precedent (no retry route for ReportExportStatus::Failed) and avoids re-deriving whether committed_through/counts are safe to resume from a user-triggered retry versus the job's own automatic $tries=3.

5. Preview aggregate counts (ready/warning/error/skipped) are a distinct concept from ProcessImportRun's commit-time counts (created/updated/skipped/errored, per KOL-94.4) -- both land on ImportRun but as separate fields, since one measures validation outcome pre-commit and the other measures the actual upsert outcome.

All routes sit behind auth + the Import:Employee permission KOL-94.6 will name, and ImportRun is scoped to the acting user's organization_id like every other resource in this app.

Out of scope for this ticket: the exact numeric preview_sync_threshold value(s) (an implementation-time config default, not an architectural decision); the auto-mapping guess algorithm invoked inside store() (KOL-94.7); the error-report CSV's column format (KOL-94.8); the wizard's actual React screens/props (KOL-94.9).
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
created: 2026-09-03 20:06
---
Addendum from KOL-94.8: the route table above is missing the template-download route, decided when pinning down the template format. Add `GET imports/employee/template/{format}` (ImportWizardController::template(), same auth + Import:Employee gate as every other route here) alongside store/show/updateMapping/updateStrategy/preview/commit/downloadErrorReport. Additive only -- nothing above changes.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
ImportWizardController exposes one route per wizard step (store/show/updateMapping/updateStrategy/preview/commit) plus an error-report download, all keyed off ImportRun's own status field. An over-threshold file is rejected at upload rather than given a queued-preview sub-path; going back to an earlier step is allowed and demotes status, clearing preview_counts; commit flips status to Processing itself before dispatching ProcessImportRun to avoid a double-submit race; Failed is terminal with no retry route. Full route table and guard rules in Implementation Notes.
<!-- SECTION:FINAL_SUMMARY:END -->
