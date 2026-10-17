---
id: KOL-157
title: Migrate /my/workdays and /my/documents tables to the shared DataTable
status: Done
assignee: []
created_date: '2026-10-17 19:57'
updated_date: '2026-10-17 20:19'
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
- [x] #1 The /my/workdays table uses the shared DataTable with sortable column headers and pagination, matching /my/leaves
- [x] #2 The /my/documents table uses the shared DataTable with sortable column headers and pagination, matching /my/leaves
- [x] #3 Existing cell content (status badges, row highlighting, empty state, download/sign actions) is preserved
- [x] #4 Tables render correctly on narrow viewports and in dark mode
- [x] #5 Existing feature tests for both pages pass; tests updated if props or behaviour change
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
1. Server-side sort + paginate(15) in My\WorkdayController and My\DocumentController (ResolvesTableSort). 2. DataTable: add rowClassName/onRowClick. 3. Rewrite both pages with DataTable columns. 4. Update/add Pest tests.
<!-- SECTION:PLAN:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Migrated /my/workdays and /my/documents to the shared DataTable with server-side sorting and pagination; added rowClassName/onRowClick to DataTable; fixed per_page being ignored on all three /my list controllers (incl. leaves).
<!-- SECTION:FINAL_SUMMARY:END -->
