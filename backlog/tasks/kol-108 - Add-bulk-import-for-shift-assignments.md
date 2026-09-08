---
id: KOL-108
title: Add bulk import for shift assignments
status: To Do
assignee: []
created_date: '2026-09-06 10:31'
updated_date: '2026-09-08 09:30'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-107
ordinal: 95000
---
## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Resolve employee by the same reference-field convention as Employee import (RUT or email) and shift by name. Reject a row whose date range overlaps an existing active assignment for the same employee as a row-level error rather than silently creating a conflicting assignment.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 ShiftAssignmentImportSchema implements the ImportSchema interface: fields, validation rules, reference resolution for employee and shift, match-key lookup, and target model
- [ ] #2 Import strategies (CreateOnly, UpdateOnly, CreateAndUpdate) behave consistently with the Employee import's semantics
- [ ] #3 A row whose date range overlaps an existing active ShiftAssignment for the same employee is flagged as an Error, not silently created
- [ ] #4 Created/updated assignments go through normal Eloquent model events so ShiftAssignmentObserver's workday recalculation fires
- [ ] #5 Import:ShiftAssignment permission gates the wizard entry point, following the Import:Employee pattern (KOL-94.6)
- [ ] #6 Template download and error-report CSV are labeled with Shift Assignment field names
- [ ] #7 Feature tests cover create, update, overlap-rejection, and reference-resolution-failure cases
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
