---
id: KOL-100
title: Add the strategy and match-key selection step to the Employee import wizard
status: To Do
assignee: []
created_date: '2026-09-03 20:44'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-99
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: feature
ordinal: 87000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Third wizard step: the user chooses what the import is allowed to do (CreateOnly/UpdateOnly/CreateAndUpdate) and, when relevant, which match key identifies existing records. Depends on KOL-99's mapping step existing.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Strategy screen offers CreateOnly, UpdateOnly, and CreateAndUpdate; a match-key picker (RUT/Email/ID, per EmployeeImportSchema's match-key-eligible fields) appears only for UpdateOnly/CreateAndUpdate and is required before saving in that case
- [ ] #2 PATCH imports/{importRun}/strategy persists strategy and match_key on the ImportRun, guarded the same way as the mapping endpoint (allowed only while status permits editing)
- [ ] #3 Feature tests cover: saving CreateOnly without a match key succeeds, saving UpdateOnly without a match key is rejected, and saving strategy+match key persists correctly and keeps the run at MappingReview
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
