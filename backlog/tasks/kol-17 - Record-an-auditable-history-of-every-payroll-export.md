---
id: KOL-17
title: Record an auditable history of every payroll export
status: Done
assignee: []
created_date: '2026-08-04 11:12'
updated_date: '2026-09-01 15:23'
labels:
  - payroll-reports
  - backend
  - frontend
milestone: m-0
dependencies: []
documentation:
  - docs/prd-reports.md
priority: medium
type: feature
ordinal: 16000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-6 and user story 5: a tenant admin must be able to see which reports were generated, by whom and when — for internal audit, and potentially as evidence toward the DT.

Required fields per the PRD: user, timestamp, report type, period consulted, format, and the filters applied. KOL-14 adds one more that matters: whether the export was confirmed over an integrity warning, and what that warning contained. An export that went out with 12 unresolved days is exactly the one someone will later need to explain.

Do not build a parallel audit mechanism. `spatie/laravel-activitylog` is already a dependency, `app/Models/Concerns` and `resources/js/components/activity-timeline.tsx` show how this project already records and renders activity, and section 8 of the PRD explicitly says to extend the existing log rather than duplicate it. Confirm during implementation whether the activity log's shape carries the structured filter payload well enough, or whether a dedicated table is genuinely warranted; if you deviate, record the reason.

Visibility requirement is specific and easy to get wrong: the log must be readable by the **tenant admin**, not only by superadmin.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Every payroll export records user, timestamp, report type, period, format and the filters that were applied
- [x] #2 An export that proceeded past an integrity warning records that fact and what was unresolved at the time
- [x] #3 The existing activity log is extended rather than a parallel audit system being built, or the deviation is justified in the notes
- [x] #4 A tenant admin can view their organization's export history from the UI, in Spanish, without superadmin access
- [x] #5 The history is organization-scoped; a test proves one tenant's exports are never visible to another
- [ ] #6 Both synchronous and queued exports are recorded, including exports whose job later failed
- [x] #7 The history view is paginated and filterable by date and report type, using the existing DataTable pattern
- [x] #8 Pest tests cover the recorded fields, the warned-and-confirmed case, tenant isolation, and admin visibility
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
1. Extend PayrollExportReadinessService::recordExport() to accept the raw filter criteria applied (premises/costCenters/positions/contractTypes/selectAll, or EmployeeController's own shape) and log finding_types/finding_count on the export entry itself (not just the confirmation entry) -- covers AC#1's 'filters applied' and AC#2's 'what was unresolved'. Tag recordExport with ->event('exported') and recordConfirmation with ->event('confirmed') so the history view can filter cleanly.
2. Update all 5 export call sites (PayrollSummaryReportController, WeeklyDetailReportController, PeriodMovementsReportController, OvertimeExcessReportController, EmployeeController::export) to pass their filters into recordExport.
3. New PayrollExportHistoryController@index: org-scoped (properties->organization_id), paginated (existing DataTable pattern), filterable by date range (created_at) and report_type, reading Activity where log_name=payroll_export and event=exported. Mirrors Saas/AuditLogController's shape but tenant-scoped.
4. Route GET payroll-reports/history -> payroll-reports.history, in the existing permission:View:PayrollReport group (same permission as viewing reports -- RF-6 says tenant admin, not superadmin).
5. Frontend page resources/js/pages/payroll-reports/history.tsx mirroring saas/audit-log/index.tsx: DataTable + date_from/date_to + report_type filter, columns (timestamp, user, report type, period, format, employee count, warned/confirmed badges), details dialog with raw properties (filters + finding_types).
6. Nav link in app-sidebar.tsx under the reports group, gated on canViewPayrollReports. lang/es+en ui.php: payroll_reports.history.* + nav key.
7. Pest tests: extend PayrollExportReadinessServiceTest / PayrollSummaryReportControllerTest for recorded filters+findings; new PayrollExportHistoryControllerTest covering recorded fields, warned-and-confirmed case, tenant isolation, and admin-only visibility.
8. Scope decision (confirmed with user): AC#6's 'queued exports' half is out of scope -- no payroll export queues today (KOL-16's queue mechanism is wired to DT reports only), documented as a deviation rather than built now.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Confirmed with user: AC#6 scoped to today's reality (all 5 payroll exports are synchronous); wiring payroll exports into KOL-16's queue mechanism is left as a separate follow-up, not built in this ticket.

Fixed a pre-existing, unrelated TypeScript error found while running npm run types:check (roles/index.tsx and roles/show.tsx passed a numeric Role.id where Wayfinder's route helpers expect a string, because Spatie's Role model resolves to a string-keyed route param in Wayfinder's generation). Confirmed with the user and fixed as a drive-by: both call sites now pass String(id).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Extended the existing payroll_export activity log (PayrollExportReadinessService::recordExport/recordConfirmation) to also carry the filters applied and the unresolved-finding types at export time, tagged with exported/confirmed events. Added PayrollExportHistoryController, an org-scoped, paginated, date/report-type-filterable history view (payroll-reports/history) reachable by the tenant admin under the existing View:PayrollReport permission, with a nav link and a details dialog. AC#6 (queued exports) left unchecked and documented: no payroll export queues today, so nothing to record on that path -- scoped out with the user's explicit sign-off.
<!-- SECTION:FINAL_SUMMARY:END -->
