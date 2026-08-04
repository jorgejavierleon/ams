---
id: KOL-25
title: Let users build reusable export column templates
status: To Do
assignee: []
created_date: '2026-08-04 11:15'
labels:
  - payroll-reports
  - backend
  - frontend
milestone: m-1
dependencies:
  - KOL-20
documentation:
  - docs/prd-reports.md
priority: medium
type: feature
ordinal: 24000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-3, and the first task of Fase 2. Every accountant wants the same data in a slightly different shape — their own column names, their own order, only the columns their system reads. Without this, each new destination is a code change; with it, a client configures it themselves once and reuses it every period.

Scope per PRD section 8: an `export_templates` table holding tenant, name, the field mapping as JSON, and a flag marking system templates. The flag exists so the Nubox template (KOL-25's successor task) can ship as a fixed, non-editable definition alongside the user's own.

What a template holds: which internal Kolvi field maps to which output column, what that column is called, and in what order the columns appear.

Keep the boundary the PRD draws in section 5.2 and flags again in section 10 — this is a **column mapping tool, not a formula engine**. Computed columns, conditionals and per-row expressions turn this into a mini payroll engine, which is explicitly out of scope. If a client needs a value that does not exist, that is a field to add to the aggregation service, not an expression for them to write.

A template that references a field which later stops existing must fail visibly at edit or export time rather than silently exporting a blank column into someone's payroll.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A user with the right permission can create, edit, name, reuse and delete export templates within their organization
- [ ] #2 A template defines which internal field maps to which output column, the column label, and the column order
- [ ] #3 Templates can be marked as system templates, which users can select and copy but not edit or delete
- [ ] #4 Applying a template to a report produces a file with exactly the mapped columns, in the defined order, under the defined names
- [ ] #5 Templates support no computed columns, formulas or conditional logic, per the scope boundary in the PRD
- [ ] #6 A template referencing a field that no longer exists reports the problem clearly instead of exporting a blank column
- [ ] #7 Templates are organization-scoped; a test proves one tenant cannot read or apply another tenant's template
- [ ] #8 All UI strings are in Spanish
- [ ] #9 Pest tests cover create, apply, reuse, the system-template restriction, the missing-field case and tenant scoping
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
