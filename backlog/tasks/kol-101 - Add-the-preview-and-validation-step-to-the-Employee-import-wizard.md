---
id: KOL-101
title: Add the preview and validation step to the Employee import wizard
status: To Do
assignee: []
created_date: '2026-09-03 20:44'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-100
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: feature
ordinal: 88000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Fourth wizard step: run every row through EvaluateImportRow synchronously and show aggregate validation results before committing. Also closes the loop on KOL-99/KOL-100's edit guards: touching mapping or strategy after a preview exists must invalidate that preview. Depends on KOL-100's strategy step existing.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 POST imports/{importRun}/preview requires every required field mapped and a strategy (+ match key when needed) set; it runs EvaluateImportRow across every row in the uploaded file synchronously (files are guaranteed sync-sized by the upload-time threshold from KOL-98) and persists preview_counts {ready, warning, error, skipped}; MappingReview -> PreviewReady
- [ ] #2 The preview screen shows only the aggregate Ready/Warning/Error/Skipped counts — never a per-row grid or list
- [ ] #3 Resubmitting PATCH imports/{importRun}/mapping or PATCH imports/{importRun}/strategy while the run is PreviewReady demotes it back to MappingReview and clears preview_counts, re-locking the commit step until preview is rerun
- [ ] #4 Feature tests cover: previewing a clean fixture yields all-Ready counts, previewing a fixture with an unresolved reference / a required-field gap / a duplicate match-key yields the expected Error/Warning breakdown, and editing mapping (or strategy) after PreviewReady demotes the run and clears preview_counts
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
