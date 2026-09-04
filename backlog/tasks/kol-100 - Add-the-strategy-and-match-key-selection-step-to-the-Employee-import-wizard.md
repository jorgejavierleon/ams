---
id: KOL-100
title: Add the strategy and match-key selection step to the Employee import wizard
status: Done
assignee:
  - '@jorge'
created_date: '2026-09-03 20:44'
updated_date: '2026-09-04 12:54'
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
- [x] #1 Strategy screen offers CreateOnly, UpdateOnly, and CreateAndUpdate; a match-key picker (RUT/Email/ID, per EmployeeImportSchema's match-key-eligible fields) appears only for UpdateOnly/CreateAndUpdate and is required before saving in that case
- [x] #2 PATCH imports/{importRun}/strategy persists strategy and match_key on the ImportRun, guarded the same way as the mapping endpoint (allowed only while status permits editing)
- [x] #3 Feature tests cover: saving CreateOnly without a match key succeeds, saving UpdateOnly without a match key is rejected, and saving strategy+match key persists correctly and keeps the run at MappingReview
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
1. Add PATCH imports/{importRun}/strategy route + ImportWizardController::updateStrategy (validate strategy enum + match_key required_if UpdateOnly/CreateAndUpdate, in EmployeeImportSchema's match-key-eligible fields; same MappingReview/PreviewReady guard as mapping endpoint; drop match_key for CreateOnly). 2. Pass strategy/match_key + isMatchKeyEligible on schemaFields from show(). 3. Add StrategyStep component (card strategy picker + match-key buttons, mirrors KOL-94.9 prototype) and wire a client-only mapping/strategy sub-step in show.tsx (both live under status=MappingReview). 4. Add es/en translations. 5. Feature tests for save paths + guard + cross-user + unsupported match key.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified end-to-end in browser: uploaded a CSV, completed mapping review, transitioned client-side to the strategy step, selected UpdateOnly + RUT match key, confirmed persistence via DB query (strategy=update_only, match_key=rut, status stayed mapping_review). Verified CreateOnly hides the match-key picker and the Back button returns to mapping without a server round trip. No console errors. Full suite: 1373/1377 passed (4 pre-existing skips).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added PATCH imports/{importRun}/strategy (ImportWizardController::updateStrategy) persisting strategy+match_key with the same MappingReview/PreviewReady guard as the mapping endpoint, and a client-side StrategyStep in the wizard's show.tsx (card strategy picker + match-key buttons) reachable after mapping is saved. Verified with 7 new Pest tests plus a full live browser walkthrough (upload -> mapping -> strategy -> save UpdateOnly+RUT -> confirmed persistence, CreateOnly hides the picker, Back returns to mapping). Full suite 1373/1377 passed (4 pre-existing skips), pint clean, types clean aside from 2 pre-existing unrelated errors. Manual QA deferred to docs/QA_CHECKLIST.md per user request.
<!-- SECTION:FINAL_SUMMARY:END -->
