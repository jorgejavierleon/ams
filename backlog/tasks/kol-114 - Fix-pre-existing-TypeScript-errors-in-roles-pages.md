---
id: KOL-114
title: Fix pre-existing TypeScript errors in roles pages
status: To Do
assignee: []
created_date: '2026-09-08 10:16'
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
- [ ] #1 npm run types:check passes with zero errors in roles/index.tsx and roles/show.tsx
- [ ] #2 Role ids are passed to Wayfinder route helpers as numbers (or the helper call is otherwise corrected) without introducing a runtime regression on the Roles list/manage pages
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
