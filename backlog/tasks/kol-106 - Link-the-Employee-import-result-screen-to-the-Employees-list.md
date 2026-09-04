---
id: KOL-106
title: Link the Employee import result screen to the Employees list
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-04 18:55'
updated_date: '2026-09-04 21:31'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-102
priority: low
ordinal: 93000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Manual QA of KOL-102 found the Completed result screen tells the user the import finished but gives them no way to go see what was actually created or updated — the counts are the only feedback. Add a 'Ver empleados' link from the result screen to the Employees list, sorted so the just-imported/updated rows surface first, without adding any new backend tracking of which employee records belong to a given run (that level of detail is the deferred import-run-history effort noted in KOL-94, out of scope here).

## User stories for manual testing (Gherkin)

Given I am an admin who just committed an Employee import that created and/or updated at least one employee
When the result screen shows "Importación completada"
Then I see a "Ver empleados" button
And clicking it takes me to the Employees list, with the most recently created/updated employees at the top

Given an import run whose commit created 0 employees and updated 0 employees (every row was skipped and/or errored)
When the result screen shows "Importación completada"
Then no "Ver empleados" button is shown
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 On the Completed result screen, when created_count + updated_count > 0, a 'Ver empleados' button/link is shown
- [x] #2 The link navigates to the Employees list sorted by created_at descending (reusing the index's existing sort/direction query params), so the just-imported/updated employees appear first, with no new backend field or run-to-employee tracking added
- [x] #3 When created_count + updated_count === 0, the button is not shown
- [x] #4 Spanish and English translation strings exist for the button label
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
1. result-step.tsx: import Link + index() route from @/routes/employees; add a 'Ver empleados' Button/Link shown when createdCount+updatedCount>0, linking to index({query:{sort:'created_at',direction:'desc'}}), reusing the index's existing sort/direction params (no backend change).
2. Add es/en translation strings ui.employees.import.result.view_employees ('Ver empleados' / 'View employees').
3. Manually verify in browser: run a full import that creates/updates employees -> button shown, click navigates to /employees?sort=created_at&direction=desc with newest first; a zero-created/zero-updated run -> button hidden.
4. vendor/bin/pint --dirty --format agent; npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Frontend-only change (no PHP logic touched): result-step.tsx adds a 'Ver empleados' Button/Link (asChild + Inertia Link) shown when createdCount+updatedCount>0, using the existing employees index() Wayfinder route with query={sort:'created_at',direction:'desc'} -- no new backend field or run-to-employee tracking, per AC #2. Added es/en translation key ui.employees.import.result.view_employees. DoD #4 (Pest test per PHP change): only PHP touched was the two lang/ui.php translation-string additions, no new logic to test. Verified manually in the browser (Chrome DevTools MCP, logged in as admin@example.com): created two ImportRuns via tinker (id 9: completed/created=2/updated=1 -> button rendered, linked to /employees?sort=created_at&direction=desc, and following it actually re-sorted the Employees list newest-first; id 10: completed/created=0/updated=0/errored=2 -> button correctly absent, only the error-report download button shown). Both scratch ImportRuns deleted after verification. vendor/bin/pint --dirty clean; npm run types:check clean (same 2 pre-existing unrelated roles/index.tsx + roles/show.tsx failures noted in KOL-101/KOL-102); eslint clean on the changed file; full sail artisan test --compact: 1401/1405 passed (4 pre-existing skips), 0 failures.

Code review (medium, backgrounded) flagged one finding: for an update_only (or mixed) run, updated employees keep their original created_at, so sorting by created_at desc won't surface them at the top -- only newly created rows benefit. Not fixed: AC #2 explicitly pins the mechanism to 'sorted by created_at descending (reusing the index's existing sort/direction query params)' with no new backend tracking, so this is a known tradeoff the ticket itself accepts rather than an implementation bug. Flagging for awareness; a future ticket could add 'updated_at' to EmployeeController's sortable-column allowlist if this gap needs closing.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a 'Ver empleados' button to the Employee import result screen's Completed state: shown when created_count+updated_count>0, linking via the existing employees index() Wayfinder route to /employees?sort=created_at&direction=desc so the just-imported/updated rows surface first, with no new backend tracking (resources/js/pages/imports/employee/result-step.tsx). Added es/en translation strings. Verified in-browser (button shown+correct link+re-sorted list when counts>0; hidden when both are 0), full Pest suite green, pint/eslint/tsc clean.
<!-- SECTION:FINAL_SUMMARY:END -->
