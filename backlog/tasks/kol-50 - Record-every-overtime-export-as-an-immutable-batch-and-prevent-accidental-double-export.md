---
id: KOL-50
title: >-
  Record every overtime export as an immutable batch and prevent accidental
  double export
status: To Do
assignee:
  - '@jorgejavierleon'
created_date: '2026-08-06 02:55'
updated_date: '2026-09-10 09:13'
labels:
  - overtime
  - backend
  - frontend
  - compliance
milestone: m-2
dependencies:
  - KOL-49
  - KOL-17
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 1500
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD sections 7.7 and 8. Generating an export is an event with legal weight — it is the moment a figure becomes a payment obligation — so it produces a permanent record of what was sent and when, and the same period cannot be exported twice by accident.

The batch stores an **immutable snapshot** of the lines as they were at generation time, not a query that re-runs later. This is the difference between an export that can be reconciled against a payroll run six months later and one that quietly reports different numbers once a workday is recalculated. Zero incidents of duplicate export for the same period is one of the PRD success metrics.

What lands:
- A batch record: period, generation timestamp, generating user, line count.
- Immutable lines snapshotting exactly what was exported.
- A guard on re-exporting a period already exported, which warns and requires explicit confirmation rather than refusing outright — a client re-running a period after a correction is a legitimate case, and both runs need to be on the record.
- Output as structured CSV and Excel per PRD section 7.7.

**Check KOL-17 before starting.** That task builds the general payroll export audit history, and this is the same concern for the overtime slice. If KOL-17 has landed, extend it rather than building a parallel batch table; if it has not, coordinate the shape so the two do not diverge. Likewise the multi-format writing belongs to KOL-15, and `app/Services/Reports/DtReportExporter.php` already writes Excel, PDF and Word for the Resolución 38 reports.

The optional API or webhook for direct payroll integrations named in the PRD is out of scope here — KOL-27 and KOL-28 already cover scoping that.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Generating an export produces a batch record carrying the period, the generation timestamp, the generating user and the line count
- [ ] #2 The exported lines are snapshotted immutably, so a later recalculation of a workday never changes what a past export says
- [ ] #3 Re-exporting a period that was already exported warns the user and proceeds only on explicit confirmation, and both runs remain on the record
- [ ] #4 Exports are produced as structured CSV and Excel
- [ ] #5 The batch reuses the general payroll export audit history from KOL-17 rather than introducing a parallel one, or the divergence is justified in the notes
- [ ] #6 The export history is viewable in Spanish and is organization-scoped
- [ ] #7 Pest tests cover a first export, a re-export of the same period, a snapshot surviving a workday recalculation unchanged, and tenant isolation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Scope decision (confirmed with user): no full employee-selector report screen. This export is 'every payable approved overtime hour, org-wide, for a period' -- a payroll obligation, not a filterable analytical report -- so the screen is just a period picker + Excel/CSV export buttons, no employee/premise/cost-center filters.
2. Reuse KOL-17's payroll_export activity log rather than a new OvertimeExportBatch/OvertimeExportLine table pair (per the ticket's own guidance, since KOL-17 has landed): the 'batch record' is the Activity row itself (causer = generating user, created_at = generation timestamp, properties.line_count), and 'immutable lines' are the export's row snapshot stored in properties.lines -- activitylog rows are never updated after insert, so this satisfies AC#2 without new tables.
3. New App\Services\Overtime\OvertimeExportReportBuilder: wraps OvertimeExportDataset::forPeriod() over every org employee id, maps each OvertimeExportLine to a display/snapshot row (employee name, rut, date, hours HH:MM:SS, day type label, pact reference display, approver name, approved_at) plus a total (line count, total hours). One shape serves the on-screen table, the exported file, and the audit-log snapshot.
4. New App\Services\Reports\OvertimeExportReportExporter (FORMATS = ['excel','csv'] only, per AC#4), mirroring PayrollSummaryReportExporter but no PDF: renders resources/views/exports/payroll/overtime.blade.php via the shared ReportWriter.
5. Extend PayrollExportReadinessService:
   - recordExport(): add an optional `lines` param (default []), stored as properties.lines + properties.line_count when non-empty -- backward compatible, other 4 call sites untouched.
   - new priorExport(reportType, start, end): ?Activity -- latest prior 'exported' activity for this org/report_type/exact period, or null. Used only by the new controller (existing reports don't get this guard).
6. New App\Http\Controllers\OvertimeExportReportController:
   - index(): resolves period (same ReportPeriod pattern as sibling controllers), builds the report for all org employees, looks up priorExport('overtime', ...) to surface a 'this period was already exported by X on Y' banner. Inertia page payroll-reports/overtime.
   - export($format): re-checks priorExport; if found and !confirmed, 422 json (same confirm-required pattern as the other exporters); on proceed, recordExport(..., lines: $report['rows']) then delegates to the exporter.
   - Permissions: index behind View:PayrollReport, export behind Export:PayrollReport (same groups as the other reports in routes/web.php).
7. Routes: GET payroll-reports/overtime (payroll-reports.overtime), GET payroll-reports/overtime/export/{format} (payroll-reports.overtime.export), in the existing two route groups.
8. Add 'overtime' to PayrollExportHistoryController::REPORT_TYPES so it shows up in the existing export-history screen/filter (AC#6).
9. Lang (es+en ui.php): payroll_reports.types.overtime, payroll_reports.descriptions.overtime, a payroll_reports.overtime.* block (columns, export labels, no_rows/total_row, prior-export warning copy), nav.payroll_export_overtime.
10. Frontend: resources/js/pages/payroll-reports/overtime.tsx (period selector + Generar + table/total + prior-export warning banner with confirm checkbox + Excel/CSV export buttons, mirroring summary.tsx's fetch/blob-download/422-toast handling). Row/total types added to payroll-reports/types.ts. Nav link added to app-sidebar.tsx between overtime-excess and history. Regenerate Wayfinder (npm run build).
11. Tests (new tests/Feature/OvertimeExportReportControllerTest.php, mirroring OvertimeExcessReportControllerTest.php's helpers): first export succeeds and is recorded with line snapshot + line_count; re-exporting the same period without confirmed=1 is rejected (422) and both exports still land as separate activity rows once confirmed; a workday recalculation after export leaves the recorded snapshot's hours unchanged; tenant isolation (org B's approved hours never appear in org A's export); permission gating (View without Export forbidden on download); csv/excel content-type + content assertions.
12. Verify: vendor/bin/pint --dirty --format agent; ./vendor/bin/sail artisan test --compact --filter=OvertimeExportReport (then broader Overtime/PayrollExport suites); npm run types:check on touched files.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Paused 2026-09-10: implementation was started (batch record via KOL-17 activity log, immutable line snapshot, re-export guard, standalone overtime.tsx screen) then discarded uncommitted after review found this report overlaps heavily with payroll-summary and overtime-excess, which already source the same four overtime buckets from OvertimePayBucketClassifier/OvertimeExportDataset. Current payroll-reports screens (summary, overtime-excess, overtime, period-movements) are confusingly overlapping and not confirmed useful to payroll as split. Decision: leave all reports as-is for now rather than build a fourth overlapping one or a premature consolidation; revisit report design (possibly one consolidated payroll export) once real payroll users give feedback on what they actually need.
<!-- SECTION:NOTES:END -->
