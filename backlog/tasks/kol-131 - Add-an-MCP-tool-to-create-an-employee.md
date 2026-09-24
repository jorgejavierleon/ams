---
id: KOL-131
title: Add an MCP tool to create an employee
status: To Do
assignee: []
created_date: '2026-09-24 19:33'
labels:
  - mcp
  - employees
  - backend
dependencies:
  - KOL-126
  - KOL-95
priority: medium
type: feature
ordinal: 131000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Lets an admin's agent create an employee (a `User` row with the `employee` role assigned), mirroring `EmployeeController::store`'s existing validation exactly — same required/optional fields (name, RUT, email, cost center, position, supervisor, contract dates/type, vacation days, timezone, etc.), same defaults, no new fields and no relaxed validation.

`EmployeeController`'s create/store today is gated only by `role:admin` route middleware. KOL-95 ("Align Employee CRUD route gating with Spatie permissions") already tracks converting the whole Employee CRUD route group to a real permission but has been sitting undecided since 2026-09-03 — this ticket depends on it, and gates the new tool (and `EmployeeController::store`, once KOL-95 lands) on whatever permission KOL-95 settles on, rather than inventing a separate permission name here.

## User stories for manual testing (Gherkin)

Scenario: An admin's agent creates an employee with the required fields
  Given an admin has a valid MCP session
  When their agent calls the create-employee tool with all required fields (first name, last name, email, RUT, password, timezone)
  Then a new employee exists with the employee role assigned, matching what the web form would create

Scenario: An admin's agent omits a required field
  Given an admin has a valid MCP session
  When their agent calls the create-employee tool without a RUT
  Then the tool refuses with the same validation message the web form gives

Scenario: A non-admin's agent is denied
  Given a user without the required permission has a valid MCP session
  When their agent calls the create-employee tool
  Then the tool refuses
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The tool accepts the same fields, with the same required/optional split and validation rules, as EmployeeController::store
- [ ] #2 A created employee is indistinguishable from one created via the web form (role assigned, organization/company stamped, same defaults)
- [ ] #3 Authorization is gated on the permission KOL-95 introduces, not a role check
- [ ] #4 Pest tests cover success, a validation failure, and denial for a user without the permission
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
