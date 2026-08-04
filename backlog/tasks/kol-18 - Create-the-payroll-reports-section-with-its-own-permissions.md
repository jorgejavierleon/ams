---
id: KOL-18
title: Create the payroll reports section with its own permissions
status: To Do
assignee: []
created_date: '2026-08-04 11:13'
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
ordinal: 17000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The container the five RF-1 reports live in. Building it first means each report task is only a report, not also a navigation and authorisation exercise.

Payroll data is more sensitive than the attendance screens the app already exposes — hours, absences and leave reasons for the whole company. It must be gated on its own Spatie permissions rather than inherited from a general admin role. This project gates on permissions, never role names: see the comment in `database/seeders/RoleSeeder.php` and the existing `EMPLOYEE_PERMISSIONS` / `SUPERVISOR_PERMISSIONS` / `ADMIN_PERMISSIONS` constants. Add the new permissions there and grant them to the roles that should hold them.

Consider whether viewing a report and exporting it deserve separate permissions — a supervisor might legitimately read their team's hours without being allowed to produce the file that leaves the building.

The DT reports section at `routes/web.php` around line 253 and `resources/js/pages/dt/reports/index.tsx` is the pattern to follow for a section landing page listing available reports. Note that this new section is for **tenant users (RRHH/admin)** and is separate from the DT inspector portal, which is a different audience with different auth entirely — do not merge them.

Do not build a drag-and-drop report designer. The PRD says 'builder simple' and section 10 flags scope inflation into a mini payroll engine as the main risk on this feature.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A payroll reports section exists with a landing page listing the available reports, reachable from the main navigation
- [ ] #2 New Spatie permissions gate the section; no code checks a role name
- [ ] #3 The permissions are added to the role seeder and granted to the appropriate roles
- [ ] #4 A user without the permission cannot reach the section by navigating directly to its URL, and does not see it in the navigation
- [ ] #5 Whether viewing and exporting are separate permissions is decided and the reasoning recorded in the notes
- [ ] #6 All UI strings are in Spanish and follow the existing translation setup
- [ ] #7 The section is distinct from the DT inspector portal and does not alter it
- [ ] #8 Pest tests cover permitted access, denied access by direct URL, and navigation visibility
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
