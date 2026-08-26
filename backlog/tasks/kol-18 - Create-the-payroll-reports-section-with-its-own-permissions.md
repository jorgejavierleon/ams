---
id: KOL-18
title: Create the payroll reports section with its own permissions
status: Done
assignee: []
created_date: '2026-08-04 11:13'
updated_date: '2026-08-26 00:28'
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
- [x] #1 A payroll reports section exists with a landing page listing the available reports, reachable from the main navigation
- [x] #2 New Spatie permissions gate the section; no code checks a role name
- [x] #3 The permissions are added to the role seeder and granted to the appropriate roles
- [x] #4 A user without the permission cannot reach the section by navigating directly to its URL, and does not see it in the navigation
- [x] #5 Whether viewing and exporting are separate permissions is decided and the reasoning recorded in the notes
- [x] #6 All UI strings are in Spanish and follow the existing translation setup
- [x] #7 The section is distinct from the DT inspector portal and does not alter it
- [x] #8 Pest tests cover permitted access, denied access by direct URL, and navigation visibility
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
1. Add View:PayrollReport and Export:PayrollReport permissions to RoleSeeder (ADMIN_PERMISSIONS), documenting why they're separate.
2. Add PayrollReportController@index rendering a landing page listing the 5 RF-1 reports as 'coming soon' cards (real reports are KOL-20..24), gated by permission:View:PayrollReport middleware, route payroll-reports.index.
3. Add resources/js/pages/payroll-reports/index.tsx following dt/reports/index.tsx's coming_soon card pattern.
4. Add Spanish/English translations under ui.nav.payroll_reports and ui.payroll_reports.*.
5. Add nav item to AppSidebar's adminNavGroups, conditional on auth.permissions including View:PayrollReport (mirrors canManageOvertime pattern).
6. Pest test (PayrollReportSectionTest) mirroring OvertimeSectionTest: permitted access, forbidden direct URL access without permission, and permissions prop reflects the gate for nav visibility.
7. Run pint, wayfinder:generate, and sa test --compact.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
AC#5: View:PayrollReport and Export:PayrollReport are separate Spatie permissions, both granted to admin for now (the only tenant role reaching this section). Viewing a report in-app and producing a file that leaves the building are different exposure levels for company-wide payroll data (hours, absences, leave reasons) — keeping them separate lets a future role (e.g. a supervisor) view without being able to export, without a seeder/policy change. Export:PayrollReport has no route yet since no report/export exists in this container ticket; it will gate the export action(s) added in KOL-15/KOL-20..24.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a payroll-reports section: GET /payroll-reports (payroll-reports.index) gated by a new permission:View:PayrollReport middleware, rendering a landing page that lists the 5 RF-1 report types as 'coming soon' cards. View:PayrollReport and Export:PayrollReport permissions added to RoleSeeder and granted to admin; kept separate per AC#5 reasoning in the notes. Nav item added to AppSidebar, conditional on the permission, with group label 'Reportes' and item title 'Reportes de remuneraciones' per user feedback. All new/changed strings (page, nav, and the Roles>Permissions breadcrumb and permission group/labels for PayrollReport) are in Spanish via the existing translation setup. DT inspector portal untouched. Verified with: PayrollReportSectionTest (5 tests: admin access, employee/no-permission 403s, permissions prop drives nav visibility), full Pest suite (1192 passed, 4 skipped, 0 failed), pint clean, npm run types:check clean, and manual curl-based Inertia requests against the running Sail app confirming the rendered component/props/translations for both an allowed admin and a denied employee, plus the Roles>Permissions screen showing the new PayrollReport group/labels in Spanish.
<!-- SECTION:FINAL_SUMMARY:END -->
