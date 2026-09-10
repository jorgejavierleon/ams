---
id: KOL-108
title: Add bulk import for shift assignments
status: To Do
assignee: []
created_date: '2026-09-06 10:31'
updated_date: '2026-09-08 22:51'
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

## Comments

<!-- COMMENTS:BEGIN -->
author: @jorgejavierleon
created: 2026-09-08 22:51
---
Reverted a full implementation of this ticket (commit was local-only, never pushed) after realizing bulk-importing shift assignments is not legally compliant as-is.

Per docs/context/resolucion_38.txt art. 567(c), a shift change ("cambio de turno") is a "Notificación" — a formal document the employer must deliver to the employee (DocumentType::Notifications already exists in this app's Document/signature system for exactly this). Today, neither ShiftAssignmentController::store() (manual entry) nor the reverted import actually creates one — ShiftAssignment.notification_date is just a plain field feeding the DT report (ShiftChangesReportService), not an actual notification delivery mechanism.

The manual flow has the same gap, but at 1-row-at-a-time scale it's less likely to matter in practice; bulk import makes it trivial to reassign many employees' shifts with no notice at all, which is the part that's clearly not okay.

This ticket should not proceed until there's a decision on the notification mechanism (most likely: creating and publishing a DocumentType::Notifications document per affected employee, for both the manual and bulk paths). That's a bigger question than this ticket alone — probably worth its own ticket/ADR before re-attempting KOL-108.
---
<!-- COMMENTS:END -->
