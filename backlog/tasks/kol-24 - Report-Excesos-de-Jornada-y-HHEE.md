---
id: KOL-24
title: 'Report: Excesos de Jornada y HHEE'
status: Done
assignee: []
created_date: '2026-08-04 11:15'
updated_date: '2026-08-31 14:55'
labels:
  - payroll-reports
  - backend
  - frontend
  - report
milestone: m-0
dependencies:
  - KOL-12
  - KOL-15
  - KOL-19
  - KOL-41
documentation:
  - docs/prd-reports.md
priority: medium
type: feature
ordinal: 23000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-1's overtime report, at either individual or consolidated level. This is where the domain work from KOL-11 and KOL-12 becomes visible to the user: overtime **by week**, with each block marked pactada or no pactada and split into its pay buckets.

Weekly rather than daily grouping is deliberate and is not a display preference — Chilean overtime limits are framed per day (art. 31) while the ordinary jornada is capped per week, and the 40-hour law makes the weekly view the one an employer is actually managing against. Buk ships a 'reporte de excesos de jornada semanal' for the same reason. Confirm how the current weekly hour cap applies for the tenants Kolvi serves before fixing the threshold in code, and put the finding in the notes.

The report has two audiences and both matter: RRHH checking nobody is drifting into unauthorised overtime, and the accountant needing the payable total per bucket. Make sure unauthorised hours are prominent rather than a footnote — they are the ones that are not going to be paid, and the ones that create legal exposure if they keep appearing.

Formats: Excel and PDF per RF-1.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Overtime is presented grouped by week for a single employee or consolidated across a selection
- [x] #2 Each entry is marked pactada or no pactada and split into its pay buckets, using the classifier rather than raw extra_time
- [x] #3 Unauthorised overtime is visually prominent and separated from payable totals
- [x] #4 Weeks exceeding the applicable statutory limit are flagged, with the limit confirmed and its basis recorded in the notes
- [x] #5 Weeks that straddle a period boundary are handled by an explicit documented rule
- [x] #6 The report exports to Excel and PDF matching the screen
- [x] #7 The export is recorded in the export audit history
- [x] #8 All labels are in Spanish
- [x] #9 Pest tests cover authorised-only, unauthorised-only and mixed weeks, a week over the limit, and the boundary case
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
1. App\Services\Reports\OvertimeExcessReportBuilder::build(start, end, userIds): resolve employees via User::where(org)->whereIn(id, userIds) (org-scoped list drives everything downstream, mirroring PayrollExportReadinessService), expand the period to whole Monday-Sunday weeks (same rule as WeeklyDetailReportBuilder/KOL-41 -- AC#5). For each week call OvertimePayBucketClassifier::forPeriod(weekStart, weekEnd, userIds) (KOL-12 -- never workdays.extra_time, AC#2) and build one row per employee: the 3 payable ("pactada") buckets (ordinaryDayHours, sundayOrHolidayHours, compensatedInRestDaysHours) + payableTotalHours, plus unauthorizedHours ("no pactada", kept as its own prominent field per AC#3) + totalHours (payable+unauthorized). Per-row capExceeded: totalHours > LegalHourLimits::forWeekOf(weekStart)->max_overtime_weekly_hours -- evaluated PER EMPLOYEE PER WEEK because art. 31's 12h/week ceiling is a per-worker limit, not a company aggregate (a consolidated selection must never sum employees together before comparing against the cap). Week carries weeklyOvertimeCapHours + legalReference (from the same LegalHourLimit row, for AC#4's "basis recorded") + employeesOverCapCount + an aggregate total row (payable/unauthorized sums, informational only, not compared to the cap).
2. App\Services\Reports\OvertimeExcessReportExporter: Excel+PDF only (mirrors WeeklyDetailReportExporter), shared ReportWriter/document.blade shell, one Blade fragment (resources/views/exports/payroll/overtime-excess.blade.php) renders screen+file identically. One table per week; unauthorized column visually separated (own column, own subtotal) from payable columns.
3. App\Http\Controllers\OvertimeExcessReportController: mirrors PayrollSummaryReportController (consolidated pattern -- works for any employee count including exactly 1, unlike WeeklyDetailReportController's exactly-one restriction, since AC#1 requires "individual or consolidated" from the same shape). Reuses ReportEmployeeSelector, PayrollExportReadinessService (confirm+audit, recordExport reportType 'overtime-excess'), ResolvesReportEmployeeFilters. Routes under the existing payroll-reports View/Export permission groups: GET payroll-reports/overtime-excess, GET payroll-reports/overtime-excess/export/{format}.
4. Frontend: resources/js/pages/payroll-reports/overtime-excess.tsx reusing PeriodSelector/ReportFilterForm/CollaboratorFilter unchanged; one Card per week (like weekly-detail.tsx) with a table of employee rows (like summary.tsx) -- payable buckets, a visually distinct "no pactada" column (destructive-styled), a per-row cap-exceeded badge, and a week-level badge when employeesOverCapCount > 0. Add types to types.ts, add nav item to app-sidebar.tsx, run wayfinder:generate.
5. Spanish/English labels under ui.payroll_reports.overtime_excess.* (es/en) -- types.overtime-excess and descriptions.overtime-excess already exist from prior work, reuse them; add filters/columns/week_label/cap_exceeded/legal_basis/pactada/no_pactada keys following the weekly_detail/summary namespaces' pattern (each report duplicates its own generate/export/no_rows strings rather than cross-referencing).
6. Pest tests tests/Feature/OvertimeExcessReportControllerTest.php: an authorised-only week (pactada only), an unauthorised-only week (no pactada only, prominent), a mixed week, a week over the weekly overtime cap for one employee flagged while another employee's week in the same period is not (proves per-employee evaluation, not aggregate), a week straddling the period boundary (period start/end mid-week still renders the whole Mon-Sun week), individual (1 employee) and consolidated (multiple employees) selections, both export formats content-matching the screen, export audit log record, View/Export permission split, org scoping.
7. vendor/bin/pint --dirty --format agent, wayfinder:generate, npm run types:check, sa test --compact.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as App\Services\Reports\OvertimeExcessReportBuilder/Exporter + App\Http\Controllers\OvertimeExcessReportController, mirroring PayrollSummaryReportController's consolidated pattern (works for 1 or many employees, unlike WeeklyDetailReportController's exactly-one restriction) since AC#1 requires "individual or consolidated" from the same shape.

Domain decisions recorded here per AC#4/#5/#2:
- AC#5 (week straddling boundary): the requested period is expanded to whole Monday-Sunday weeks (same rule as WeeklyDetailReportBuilder and LegalHourLimits::forWeekOf()) — a week starting/ending outside the nominal period still renders in full rather than splitting across two partial rows. Tested explicitly (1 Aug 2026 pulls in the week starting 27 Jul).
- AC#4 (applicable statutory limit + basis): the flagged cap is max_overtime_weekly_hours from LegalHourLimits::forWeekOf(weekMonday) — a constant 12h across every version in the legal_hour_limits baseline migration (only ordinary_weekly_hours is date-versioned by Ley 21.561; the overtime ceilings are not). Critically, this is evaluated PER EMPLOYEE PER WEEK, never aggregated across a consolidated selection — art. 31's 12h/week ceiling is a per-worker limit, so summing many employees' hours before comparing to it would be wrong (e.g. 50 employees each individually within cap must never read as "over the limit" because their hours summed exceed 12h). Each row carries its own capExceeded; the week aggregates only a count (employeesOverCapCount) for at-a-glance scanning. Verified with a dedicated test asserting one employee flagged and another (in the same week) not, even though their combined total would exceed 12h.
- AC#2 (pactada/no pactada): pactada = the three payable buckets from OvertimePayBucketClassifier (KOL-12): ordinaryDayHours, sundayOrHolidayHours, compensatedInRestDaysHours (summed as payableTotalHours); no pactada = unauthorizedHours (KOL-46's remainder). Classifier called once per week (not once per period, unlike PayrollSummaryReportBuilder) so the weekly cap check has a week-scoped total to compare against.
- AC#3 (unauthorised prominent): unauthorizedHours is kept as its own field, never blended into payableTotalHours; the frontend renders it in a visually distinct (destructive-styled) column separate from the payable columns.

17 new Pest tests (tests/Feature/OvertimeExcessReportControllerTest.php): authorised-only, unauthorised-only, mixed week, per-employee cap flagging in a consolidated selection, period-boundary week, consolidated vs individual selection shapes, cap version resolved by the week's Monday, both export formats content-matching the screen, export audit log, View/Export permission split, org scoping.

Verification: pint clean, phpstan (composer types:check) clean, tsc --noEmit clean, eslint clean (one pre-existing unrelated warning in use-server-table.ts). Full suite: 1312/1316 passed, 4 pre-existing skips, 0 failures. Adjacent suites (WeeklyDetailReportControllerTest, PayrollSummaryReportControllerTest, OvertimePayBucketClassifierTest) re-run together with no regressions.
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @planning
created: 2026-08-06 02:57
---
Scope note from the overtime module planning (m-2): the weekly-limit question this task raises in AC #4 and #5 is settled upstream — KOL-41 defines the week used for the weekly overtime cap and documents the straddling-period rule, and KOL-36 makes the limit itself date-versioned (45h until 2026-04-25, 42h from 2026-04-26 under Ley 21.561, 40h thereafter). Consume both rather than fixing a threshold in this report. The pactada / no pactada split comes from the authorisation record in KOL-11.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Shipped the 'Excesos de Jornada y HHEE' report (RF-1): overtime grouped by Monday-Sunday week, individual or consolidated, split into pactada (per pay bucket via OvertimePayBucketClassifier, KOL-12) and no pactada (KOL-46, visually prominent). The weekly legal overtime cap (12h, LegalHourLimits::forWeekOf) is evaluated per employee per week — never aggregated across a consolidated selection, since art. 31's ceiling is a per-worker limit. Exports to Excel/PDF via the shared ReportWriter and records every export in the payroll_export audit log. 17 new Pest tests; full suite 1312/1316 passed (4 pre-existing skips); pint, phpstan and tsc/eslint all clean.
<!-- SECTION:FINAL_SUMMARY:END -->
