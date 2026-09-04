---
id: KOL-106
title: Link the Employee import result screen to the Employees list
status: To Do
assignee: []
created_date: '2026-09-04 18:55'
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
- [ ] #1 On the Completed result screen, when created_count + updated_count > 0, a 'Ver empleados' button/link is shown
- [ ] #2 The link navigates to the Employees list sorted by created_at descending (reusing the index's existing sort/direction query params), so the just-imported/updated employees appear first, with no new backend field or run-to-employee tracking added
- [ ] #3 When created_count + updated_count === 0, the button is not shown
- [ ] #4 Spanish and English translation strings exist for the button label
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
