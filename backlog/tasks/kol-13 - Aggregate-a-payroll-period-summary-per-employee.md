---
id: KOL-13
title: Aggregate a payroll period summary per employee
status: Done
assignee: []
created_date: '2026-08-04 11:11'
updated_date: '2026-08-24 10:10'
labels:
  - payroll-reports
  - backend
milestone: m-0
dependencies:
  - KOL-12
  - KOL-49
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
- [x] #1 A service returns, per employee for a given period, at minimum: horas trabajadas, horas no trabajadas, horas extra by bucket, total atraso, count and days of justified vs unjustified absence, Sundays and holidays worked, and a días pagados / días no pagados split by reason
- [x] #2 The justified-vs-unjustified absence definition matches what AttendanceReportService already produces for the DT report; the two do not disagree for the same employee and date
- [x] #3 The summary can be produced for an arbitrary period, including a quincena that does not align to month boundaries
- [x] #4 Aggregating 500 employees over one month issues a bounded number of queries, not one or more per employee, and a test asserts the query count
- [x] #5 Results are organization-scoped; a test proves employees from another tenant never appear even when their ids are passed in explicitly
- [x] #6 Pest tests cover an employee with overtime, one with unjustified absences, one on medical leave, one on vacation, and one with no data in the period
- [x] #7 Field names are plain English (camelCase) rather than the PRD's Spanish naming; renaming to match GeoVictoria's Spanish field names (PRD §2.3.3) is deferred to whichever future ticket builds the API/export layer that needs that exact shape (decided by the user during review, KOL-13)
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
1. Add userId to AttendanceReportService::build() and SundaysReportService::build() return shape (additive, non-breaking) so PayrollPeriodSummaryService can key rows without relying on name matching.
2. Create App\Services\Reports\PaidDaysBreakdown and NonPaidDaysBreakdown readonly DTOs (dias_trabajados/vacaciones/permisos_con_goce; ausencias_injustificadas/licencias/permisos_sin_goce).
3. Create App\Services\Reports\PayrollPeriodSummary readonly DTO with Spanish field names per PRD 2.3.3: horas_trabajadas, horas_no_trabajadas, total_atraso, horas_extra (OvertimePayBucketBreakdown from KOL-12), ausencias_justificadas, ausencias_injustificadas, domingos_y_festivos_trabajados, dias_pagados, dias_no_pagados.
4. Create App\Services\Reports\PayrollPeriodSummaryService::build(Carbon $start, Carbon $end, array $userIds): Collection<int, PayrollPeriodSummary>:
   - Scope $userIds to the current organization via User::where('organization_id', CurrentOrganization::id()) first (User has no global org scope) so a cross-tenant id is dropped before any other query runs.
   - horas_trabajadas/horas_no_trabajadas/total_atraso: one bulk Workday query summing worked_time/missing_time and positive-only in_time_difference (atraso; a negative TIMEDIFF means early arrival, not atraso).
   - horas_extra: delegate to existing OvertimePayBucketClassifier::forPeriod (KOL-12/KOL-49), no reimplementation.
   - domingos_y_festivos_trabajados: delegate to SundaysReportService::build(), sum its 'total' field.
   - ausencias_justificadas/injustificadas and dias_pagados/dias_no_pagados: derive from AttendanceReportService::build() rows (attended/absence/observation), the exact same day-by-day resolution the DT report uses, so the two can never disagree (AC #2). Bucket leave-covered days by LeaveType: Vacation/Paid -> dias_pagados, Medical -> licencias, Unpaid/Other -> permisos_sin_goce.
5. Pest tests in tests/Feature/PayrollPeriodSummaryServiceTest.php covering AC #1-7: overtime employee, unjustified-absence employee, medical-leave employee, vacation employee, no-data employee, cross-org exclusion, query-count boundedness (DB::listen pattern from OvertimeExportDatasetTest), and a quincena period not aligned to month boundaries.
6. pint --dirty, sa test --compact on the new/touched files.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented PayrollPeriodSummaryService (App\Services\Reports) plus DTOs PayrollPeriodSummary/PaidDaysBreakdown/NonPaidDaysBreakdown. Reuses AttendanceReportService (absence justification), SundaysReportService (domingos/festivos trabajados) and OvertimePayBucketClassifier (KOL-12/KOL-49 overtime buckets) rather than recomputing. Added a 'userId' key to AttendanceReportService::build() and SundaysReportService::build() return shapes (additive, non-breaking) to key rows reliably. Org scoping done via an explicit User::where(organization_id, CurrentOrganization::id()) pre-filter, since User has no global org scope in this codebase (documented precedent in Dt/ReportController). 'horas_no_trabajadas' is scoped to sum(missing_time) only (partial shortfalls against a scheduled shift) — a fully absent day's missing_time is 0 by the existing WorkdayCalculator SQL (TIMEDIFF against NULL marks), so full-day absences surface via the ausencias_injustificadas/justificadas counts instead, not as extra horas_no_trabajadas. LeaveType::Other is folded into permisos_sin_goce (días no pagados) since it carries no paid-time-off guarantee of its own. 10 new Pest tests in tests/Feature/PayrollPeriodSummaryServiceTest.php cover all 7 ACs. Ran targeted tests only (PayrollPeriodSummaryServiceTest + DtReports*/OvertimeExportDataset/OvertimePayBucketClassifier, 82 tests, all pass) per project convention of not running the full suite until asked.

Full suite run (sa test --compact, 1144 tests): 1139 passed, 1 pre-existing failure unrelated to this ticket (Tests\Feature\Api\UpcomingShiftsApiTest::the_days_param_controls_the_horizon_and_defaults_to_14 — a date-horizon assertion that depends on today's calendar date, in a file this branch never touches). All PayrollPeriodSummaryServiceTest (10) and the reused-service suites (DtReports*, OvertimeExportDataset, OvertimePayBucketClassifier) pass.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added PayrollPeriodSummaryService (App\Services\Reports), returning a PayrollPeriodSummary per employee for an arbitrary period: workedHours, nonWorkedHours, totalLateness, overtime (by pay bucket, via KOL-12's OvertimePayBucketClassifier), justified/unjustified absence day counts, sundaysAndHolidaysWorked, and a paidDays/nonPaidDays breakdown by leave type. Reuses AttendanceReportService's day-by-day absence resolution (added a non-breaking userId key to its and SundaysReportService's output) so the two can never disagree. Organization-scoped via an explicit User pre-filter (User has no global org scope in this codebase) plus the underlying models' own BelongsToOrganization scoping. Field names were built Spanish-snake_case per the PRD first, then changed to English camelCase per user direction during review (AC #3 removed/replaced with AC #7 to record that decision) — the PRD's Spanish shape is deferred to whichever future ticket builds the API/export layer. Verified with 10 new Pest tests covering all ACs, pint clean, and a full suite run (1139/1140 relevant tests pass; the one failure is pre-existing and unrelated).
<!-- SECTION:FINAL_SUMMARY:END -->
