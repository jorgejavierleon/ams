---
id: KOL-9
title: Add cost centres as a reporting dimension
status: To Do
assignee: []
created_date: '2026-08-04 11:10'
labels:
  - payroll-reports
  - backend
  - frontend
milestone: m-0
dependencies: []
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 8000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-7 of the payroll reports PRD requires every report to be filterable by *centro de costo*, and the Nubox hand-off (RF-4) is the point where an accountant splits payroll cost across the business. **No such concept exists anywhere in the codebase today** — a grep for cost_center/costCenter/centro_costo returns nothing. Employees carry `company_id`, `premise_id` and `position_id` only.

A cost centre is not the same as a premise (sucursal): two teams working out of one premise routinely charge to different cost centres, and a single cost centre can span premises. So this needs its own model rather than reusing `Premise`.

Scope it as a plain organizational catalogue owned by the tenant, in the same shape as `Position` (see `app/Models/Position.php`, `app/Http/Controllers/PositionController.php` and `resources/js/pages/positions` for the CRUD pattern this project already uses, including the organization scope). Employees reference one cost centre, optionally — existing employees must keep working with none assigned.

This lands before the report tasks because the filter layer (KOL-18-ish) and the payroll aggregation query both need the column to exist.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A CostCenter model exists, scoped to the organization the same way Position is, with at minimum a name and an optional code the client can match to their own accounting system
- [ ] #2 Employees can be assigned a cost centre; the field is nullable so every existing employee remains valid without one
- [ ] #3 Cost centres are managed from the UI (list, create, edit, delete) following the existing Position pages, and are reachable from navigation for users with the matching permission
- [ ] #4 Deleting a cost centre that still has employees assigned does not orphan or silently reassign them; the behaviour is explicit and tested
- [ ] #5 The employee form and employee list surface the cost centre, and the employee list can be filtered by it
- [ ] #6 A factory and seeder exist so reports work can generate realistic multi-cost-centre data
- [ ] #7 Pest tests cover organization scoping (one tenant never sees another tenant's cost centres), assignment to an employee, and the delete behaviour
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
