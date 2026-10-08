---
id: KOL-114
title: Fix pre-existing TypeScript errors in roles pages
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-08 10:16'
updated_date: '2026-10-08 11:30'
labels:
  - bug
dependencies: []
ordinal: 101000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
npm run types:check fails with 2 pre-existing errors unrelated to any current work: resources/js/pages/roles/index.tsx(71,36) and resources/js/pages/roles/show.tsx(59,56) both pass a role id as a string where the Wayfinder-generated route helper (RoleController.show/update) expects number | {id: number}. Surfaced while working KOL-107 (unrelated bulk-import ticket) — these 2 files were untouched by that work, confirming the errors predate it.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 npm run types:check passes with zero errors in roles/index.tsx and roles/show.tsx
- [x] #2 Role ids are passed to Wayfinder route helpers as numbers (or the helper call is otherwise corrected) without introducing a runtime regression on the Roles list/manage pages
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
No code changes needed. roles/index.tsx and roles/show.tsx already pass role ids as numbers (Role.id: number); KOL-135 work (commits ea78834..3d67fc3, 2026-09-27) rewrote these files after this ticket was filed and resolved the original string/number mismatch as a side effect. Verified by regenerating Wayfinder (sail artisan wayfinder:generate --with-form) then running npm run types:check (0 errors) and sail artisan test --compact --filter=Role (100 passed, 467 assertions). DoD #4 (Pest test per PHP change) N/A — no PHP changes made.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Confirmed fixed by prior KOL-135 work, no code change required. Verified with a fresh Wayfinder regen + npm run types:check (clean) and the Role test suite (100 passed).
<!-- SECTION:FINAL_SUMMARY:END -->
