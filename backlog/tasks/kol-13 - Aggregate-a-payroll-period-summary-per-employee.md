---
id: KOL-13
title: Aggregate a payroll period summary per employee
status: To Do
assignee: []
created_date: '2026-08-04 11:11'
labels:
  - payroll-reports
  - backend
milestone: m-0
dependencies:
  - KOL-12
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 12000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
This is the calculation core the whole feature rests on: given a period (quincena or mes) and a set of employees, produce the per-employee figures a payroll system needs. Every RF-1 report and the Nubox export (RF-4) read from it.

Section 2.3.3 of the PRD recommends calquing the shape of GeoVictoria's `Consolidated/Extended` endpoint, which is already market-validated: horas trabajadas, horas no trabajadas, horas extra by bucket, count of inasistencias, Sundays and holidays worked, and crucially a **días pagados vs. días no pagados** split (worked days, vacations and paid time off on one side; unjustified absences, licencias and unpaid leave on the other). Use Spanish field names as the PRD recommends.

Almost all of this already exists as data and must be read, not recomputed:
- `workdays` carries `worked_time`, `extra_time`, `missing_time`, `in_time_difference` (the atraso), `out_time_difference` and `status` (`app/Enums/WorkdayStatus.php`: regular, irregular, absent, incomplete, justified).
- `app/Models/Leave.php` with `app/Enums/LeaveType.php` (vacation, medical, unpaid, paid, other) and `LeaveStatus` supplies the paid/unpaid day split.
- `app/Services/Reports/AttendanceReportService.php` already resolves justified vs unjustified absence day by day for the DT report — reuse that reasoning rather than writing a second, divergent definition of what counts as an absence.
- KOL-12 supplies the overtime buckets.

Two rules that matter: the aggregate must be computed in bulk for hundreds of employees without an N+1 (KOL-2 already flags this class of problem in this codebase), and it must respect organization scoping absolutely (RNF multi-tenancy).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A service returns, per employee for a given period, at minimum: horas trabajadas, horas no trabajadas, horas extra by bucket, total atraso, count and days of justified vs unjustified absence, Sundays and holidays worked, and a días pagados / días no pagados split by reason
- [ ] #2 The justified-vs-unjustified absence definition matches what AttendanceReportService already produces for the DT report; the two do not disagree for the same employee and date
- [ ] #3 Field names are in Spanish per PRD section 2.3.3 so the later API and exports can pass them through unchanged
- [ ] #4 The summary can be produced for an arbitrary period, including a quincena that does not align to month boundaries
- [ ] #5 Aggregating 500 employees over one month issues a bounded number of queries, not one or more per employee, and a test asserts the query count
- [ ] #6 Results are organization-scoped; a test proves employees from another tenant never appear even when their ids are passed in explicitly
- [ ] #7 Pest tests cover an employee with overtime, one with unjustified absences, one on medical leave, one on vacation, and one with no data in the period
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
