---
id: KOL-93
title: >-
  Add page-number links and a rows-per-page selector to the shared DataTable
  pagination
status: Done
assignee: []
created_date: '2026-08-31 16:04'
updated_date: '2026-08-31 19:49'
labels: []
dependencies: []
ordinal: 71000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The shared DataTablePagination component (used across every server-driven list page) only offers Previous/Next buttons and a fixed page size of 10, hardcoded per-controller as paginate(10). Add numbered page links (driven by Laravel's paginator links array, already present in the paginated JSON payload) and a rows-per-page Select that reloads the list via a per_page query param, mirroring the existing search/sort reload pattern in useServerTable. Do not adopt TanStack Table's pagination row model: manualPagination is already set, data is server-paged, and Laravel's paginator meta (current_page, per_page, links) stays the single source of truth, consistent with how sorting is already server-driven rather than TanStack-driven.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 PaginationMeta/Paginated types expose the paginator's per_page and links (url, label, active) fields
- [x] #2 DataTablePagination renders numbered page links (with ellipsis where Laravel collapses them) using Inertia Link, alongside the existing Previous/Next
- [x] #3 DataTablePagination renders a rows-per-page Select (10/25/50/100) that triggers a partial Inertia reload preserving current search/sort/filters and resets to page 1
- [x] #4 A shared concern/helper resolves and validates the per_page request param against an allow-list, defaulting to 10, for reuse across controllers
- [x] #5 Every controller currently calling ->paginate(10) for a DataTable-backed index is updated to use the resolved per_page
- [x] #6 Existing Pest feature tests for at least one updated index page cover per_page selection and page-number navigation
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
1. Create App\Concerns\ResolvesTablePerPage trait (allow-list 10/25/50/100, default 10), mirroring ResolvesTableSort.
2. Wire it into every controller currently calling ->paginate(10) for a DataTable-backed index (Dt\DocumentController, Dt\OrganizationController, Saas\OrganizationController, Saas\DocumentVarController, DocumentTemplateController, PositionController::index, RoleController, DocumentController, CostCenterController, PremiseController, ShiftController, OvertimePactController, EmployeeController).
3. Extend PaginationMeta/Paginated TS types with per_page and links (url/label/active).
4. DataTablePagination: render numbered Laravel paginator links (Inertia Link, ellipsis-aware) alongside existing Previous/Next, and a rows-per-page Select (10/25/50/100) that reloads via router.get reading/merging window.location's current query string (preserves search/sort/extraParams, drops page) with preserveState/preserveScroll/replace, honoring an optional only prop threaded from DataTable.
5. Add es/en translation key for the rows-per-page label.
6. Extend PositionManagementTest with per_page selection + page-number navigation coverage.
7. pint --dirty, sa test --compact, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Fixed a pre-existing bug found during review: the shared Select primitive (resources/js/components/ui/select.tsx) hard-coded avoidCollisions={false}, so any dropdown near the bottom of the viewport (e.g. the new rows-per-page selector) got clipped instead of flipping to open upward. Removed the override to restore Radix's default collision handling; this affects every Select in the app, not just this one.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added numbered page links and a rows-per-page selector (10/25/50/100) to the shared DataTablePagination component. Backend: new ResolvesTablePerPage concern validates per_page against an allow-list, wired into every controller that paginated a DataTable-backed index. Frontend: PaginationMeta/Paginated types expose per_page and links; the per_page Select reloads via router.get preserving current search/sort/filters and resetting to page 1. Also fixed a pre-existing Select collision bug that clipped dropdowns near the bottom of the page.
<!-- SECTION:FINAL_SUMMARY:END -->
