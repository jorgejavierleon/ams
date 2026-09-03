---
id: KOL-99
title: Add the column mapping review step to the Employee import wizard
status: To Do
assignee: []
created_date: '2026-09-03 20:44'
updated_date: '2026-09-03 20:45'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-98
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: feature
ordinal: 86000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Second wizard step: auto-map uploaded columns against EmployeeImportSchema and let the user fix/confirm them. Algorithm and UI already validated in KOL-94.7's throwaway prototype (Variant A) — reuse that shape rather than re-deriving it. Depends on KOL-98's upload step existing.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A ColumnAutoMapper scores each uploaded header against EmployeeImportSchema's fields and marks it Mapped at score >= 0.6, otherwise Unmapped; no confidence score is persisted or surfaced in the UI
- [ ] #2 The mapping-review screen is a flat table (one row per uploaded column) with an inline searchable Combobox listing every schema field plus an explicit 'Ignore this column' option, used for both Unmapped rows and fixing an already-Mapped guess
- [ ] #3 The wizard refuses to proceed past mapping while any of EmployeeImportSchema's CreateOnly-required fields is Unmapped
- [ ] #4 Feature tests cover: auto-mapping a fixture header set (including a few intentionally ambiguous/short headers) produces the expected Mapped/Unmapped/Ignored split, saving a mapping with all required fields mapped succeeds, and saving with a required field still Unmapped is rejected
- [ ] #5 PATCH imports/{importRun}/mapping persists the ColumnMapping array on the ImportRun; allowed while status is MappingReview or PreviewReady (resubmitting while PreviewReady demotes the run per KOL-101)
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
