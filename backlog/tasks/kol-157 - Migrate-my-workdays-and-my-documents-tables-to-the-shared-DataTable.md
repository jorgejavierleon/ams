---
id: KOL-157
title: Migrate /my/workdays and /my/documents tables to the shared DataTable
status: To Do
assignee: []
created_date: '2026-10-17 19:57'
labels:
  - frontend
  - ui
dependencies: []
ordinal: 183000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The employee self-service pages `/my/workdays` (resources/js/pages/my/workdays/index.tsx) and `/my/documents` (resources/js/pages/my/documents/index.tsx) still render hand-built `<Table>` markup, so they look different from the rest of the app. Admin lists (`workdays/index.tsx`, `documents/index.tsx`) and `my/leaves/index.tsx` already use the shared `DataTable` component (`@/components/data-table`, `DataTableColumnHeader`, pagination). Migrate both pages so they share the current table style and behaviour.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The /my/workdays table uses the shared DataTable with sortable column headers and pagination, matching /my/leaves
- [ ] #2 The /my/documents table uses the shared DataTable with sortable column headers and pagination, matching /my/leaves
- [ ] #3 Existing cell content (status badges, row highlighting, empty state, download/sign actions) is preserved
- [ ] #4 Tables render correctly on narrow viewports and in dark mode
- [ ] #5 Existing feature tests for both pages pass; tests updated if props or behaviour change
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
