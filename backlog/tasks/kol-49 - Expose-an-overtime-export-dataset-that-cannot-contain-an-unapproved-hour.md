---
id: KOL-49
title: Expose an overtime export dataset that cannot contain an unapproved hour
status: To Do
assignee: []
created_date: '2026-08-06 02:54'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-46
  - KOL-47
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 1400
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 7.7, and the single non-negotiable success metric of the whole module: **100% of exported overtime hours carry an explicit approval record.**

The PRD is deliberate about how that is achieved: *"This is a query/view-level constraint, not a UI convention — the technical design must make it impossible for any status other than approved to appear in the export dataset."* A `where('status', 'approved')` that a future caller can forget is not sufficient. A global scope, a dedicated read model, or a query the export cannot construct any other way — the mechanism is the implementer choice, but the property to prove is that there is no code path producing an export row from a pending or objected record.

What the dataset yields per line, for audit traceability: employee RUT, date, approved hours, day type (weekday, Sunday or holiday), the pacto reference when one applies, the approver, and the approval timestamp.

Two exclusions that are easy to get wrong: hours compensated in rest days never appear (KOL-47), and hours falling outside the final figure never appear as payable, though they may be reported separately as unauthorised.

This is the seam the payroll reports consume. KOL-12 classifies these hours into their pay buckets, KOL-13 aggregates them per employee for the period, and KOL-24 reports them by week — all three should read this dataset rather than reaching into overtime records directly.

Day type is derived from existing data: `app/Models/Holiday.php` and its seeder cover festivos, and `app/Services/Reports/SundaysReportService.php` already reasons about Sunday work for the Resolución 38 report and is worth reading before writing anything new.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A dataset returns approved overtime for an employee selection and period, with one line per employee and day
- [ ] #2 Each line carries the employee RUT, the date, the approved hours, the day type, the pacto reference when one applies, the approver and the approval timestamp
- [ ] #3 It is structurally impossible to obtain a pending or objected record from this dataset; the constraint lives in the query or read model, not in a caller convention, and the test proves the impossibility rather than the happy path
- [ ] #4 Hours compensated in rest days are excluded, and unauthorised excess never appears as payable
- [ ] #5 Day type is derived from the existing Holiday data and the existing Sunday reasoning rather than a new calendar
- [ ] #6 The dataset is organization-scoped and bounded in query count for a 500-employee period
- [ ] #7 Pest tests cover an approved period, a period containing pending and objected records, a period with rest-day compensation, a Sunday, a holiday, and an attempt to reach a non-approved record through the dataset
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
