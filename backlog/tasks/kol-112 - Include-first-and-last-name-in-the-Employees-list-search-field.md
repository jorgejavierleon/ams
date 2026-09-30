---
id: KOL-112
title: Include first and last name in the Employees list search field
status: Done
assignee: []
created_date: '2026-09-06 18:33'
updated_date: '2026-09-30 23:08'
labels:
  - employees
dependencies: []
references:
  - app/Http/Controllers/EmployeeController.php
  - tests/Feature/EmployeeManagementTest.php
ordinal: 99000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The Employees list search field (EmployeeController::filteredEmployeesQuery(), app/Http/Controllers/EmployeeController.php:334-336) only matches email and rut — confirmed by the existing test "employees can be searched by email and rut" (tests/Feature/EmployeeManagementTest.php:241) and the current placeholder text ("Buscar por email o RUT...", lang/es/ui.php:1091). A user who remembers a name but not the exact email or RUT has no way to find that employee from the list search, which is the far more common way people actually look someone up.

Extend the search to also match first_name, last_name, and second_last_name (case-insensitive partial match, same as the existing email/rut behavior), and update the search placeholder copy to mention name. This same query backs the Employees export filter (AC #7 of an earlier ticket keeps index() and export() in sync), so the export's "respects the search filter" behavior should keep working unchanged once name matching is added.

## User stories for manual testing (Gherkin)

Scenario: Finding an employee by first name
  Given there is an employee named "Ana Pérez" in the Employees list
  When I type "Ana" into the search field
  Then "Ana Pérez" appears in the results

Scenario: Finding an employee by last name
  Given there is an employee named "Ana Pérez" in the Employees list
  When I type "Perez" into the search field (no accent)
  Then "Ana Pérez" appears in the results

Scenario: Existing email/rut search still works
  Given there is an employee with a known email and RUT
  When I search by that email, and separately by that RUT
  Then the employee appears in both cases, same as before
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Searching the Employees list matches first_name, last_name, or second_last_name (case-insensitive partial match), in addition to the existing email and rut match
- [x] #2 Existing email and rut search behavior is unchanged (a search term matching only an email or only a rut still returns that employee)
- [x] #3 The Employees export (employees.export), which reuses the same filteredEmployeesQuery(), also picks up name matching automatically since both share one query builder
- [x] #4 The search placeholder copy (lang/es/ui.php employees.search_placeholder) is updated to mention name, e.g. "Buscar por nombre, email o RUT..."
- [x] #5 Feature tests cover: search by first_name alone, by last_name alone, by second_last_name alone, and confirm existing email/rut search tests still pass
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Extend filteredEmployeesQuery() in EmployeeController to also match first_name/last_name/second_last_name (like email/rut).
2. Update lang/es/ui.php employees.search_placeholder to mention name.
3. Add Pest feature tests for search by first_name, last_name, second_last_name; confirm existing email/rut tests still pass.
4. Run pint + tests.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Added orWhere on first_name/last_name/second_last_name to filteredEmployeesQuery() (shared by index() and export()). Updated es and en search_placeholder copy. Added 3 feature tests (first/last/second_last_name). DoD #3 (types:check) skipped, no TS touched.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Extended the Employees list search to match first_name, last_name, and second_last_name (in addition to email and rut), and updated the search placeholder copy in es/en. Export filter picks up name matching automatically since it shares the same query builder.
<!-- SECTION:FINAL_SUMMARY:END -->
