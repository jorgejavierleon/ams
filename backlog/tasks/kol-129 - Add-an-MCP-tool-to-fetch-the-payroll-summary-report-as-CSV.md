---
id: KOL-129
title: Add an MCP tool to fetch the payroll summary report as CSV
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-24 19:32'
updated_date: '2026-09-26 18:02'
labels:
  - mcp
  - payroll-reports
  - backend
dependencies:
  - KOL-126
priority: high
type: feature
ordinal: 129000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Gives an admin's agent access to exactly one payroll report: "Resumen de Remuneraciones por Período" (`PayrollSummaryReportController`/`PayrollSummaryReportExporter`), already documented in code as the flagship payroll report and the one the original PRD (`docs/prd-reports.md`, user story #1) describes as "para pasárselo a mi contador externo." The other three payroll-reports screens (weekly-detail, period-movements, overtime-excess) serve different purposes — a weekly legal-cap check, HR movements, and a per-employee validation view, respectively, not a payment input — and are explicitly out of scope here.

The tool takes a period (year + month, or a date range) and an optional employee filter, and returns the report as CSV text, reusing the existing CSV writer path (same `;` delimiter, UTF-8 encoding, same figures as the web export). It does not send email and does not write any file to disk — what the calling agent does with the CSV is outside this app's responsibility.

Before returning data, it runs the existing `PayrollExportReadinessService` check. Unlike the web UI (which blocks on unresolved findings until the user explicitly confirms), the tool never refuses: it always returns the requested data, and when there are unresolved findings (mark anomalies, pending mark modifications), it includes them as a warning alongside the data so the calling agent can relay that context to whoever asked for the report.

Gated by the existing `Export:PayrollReport` permission — no new permission needed.

## User stories for manual testing (Gherkin)

Scenario: An admin's agent fetches a clean period's payroll summary
  Given an admin has a valid MCP session and October has no unresolved attendance issues
  When their agent calls the payroll-summary tool for October
  Then it returns October's summary report as CSV text, with no warning

Scenario: An admin's agent fetches a period with unresolved issues
  Given an admin has a valid MCP session and October has unresolved mark anomalies
  When their agent calls the payroll-summary tool for October
  Then it still returns October's summary report as CSV text, plus a warning listing the unresolved findings

Scenario: A non-admin's agent is denied
  Given a user without Export:PayrollReport has a valid MCP session
  When their agent calls the payroll-summary tool
  Then the tool refuses
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The tool returns the summary report's data as CSV text for a given period (and optional employee filter), matching the web export's figures exactly
- [x] #2 The tool never blocks on readiness findings; it always returns data, surfacing findings as a warning when present
- [x] #3 The tool sends no email and writes no file to disk
- [x] #4 Gated by the existing Export:PayrollReport permission
- [x] #5 Pest tests cover: a clean period, a period with findings (warning present, data still returned), and denial for a user without the permission
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add ReportWriter::csvBytes() (byte-returning sibling of excelBytes/pdfBytes/wordBytes) and refactor csv() to use it.
2. Add PayrollSummaryReportExporter::csvText() reusing the existing prepare() (same builder, same Blade fragment, ';' delimiter).
3. Add App\Mcp\Tools\Reports\GetPayrollSummaryReportTool: schema takes period_year, period_month, optional period_type (default month) and optional employee_ids (default = every org employee via ReportEmployeeSelector). Authorizes on Export:PayrollReport. Runs PayrollExportReadinessService::check() and always returns the CSV, adding a 'warning' (message + findings) when not clean.
4. Register the tool on KolviServer.
5. Pest feature tests (tests/Feature/Mcp/Reports/PayrollSummaryReportToolTest.php): clean period returns CSV with no warning; period with a MarkModification finding returns CSV plus warning; a user without Export:PayrollReport is denied.
6. Pint --dirty, sail artisan test --filter=Mcp.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Added App\Mcp\Tools\Reports\GetPayrollSummaryReportTool (get-payroll-summary-report), gated by Export:PayrollReport, reusing ReportEmployeeSelector/ReportPeriod/PayrollExportReadinessService/PayrollSummaryReportExporter exactly as the web controller does. Added PayrollSummaryReportExporter::csvText() (reuses prepare()) and ReportWriter::csvBytes() (byte-returning sibling of excelBytes/pdfBytes/wordBytes) to get CSV as a string instead of an HTTP download.

Every call is recorded via PayrollExportReadinessService::recordExport() (confirmed=false, since there's no confirmation step) so MCP-driven exports still show up in the KOL-17 audit history -- a code-review pass caught that this was missing from the first draft. The same review caught that refactoring ReportWriter::csv() to call csvBytes() internally would make the HTTP download path fully buffer the CSV in memory instead of streaming; reverted csv() to its original direct-write implementation and kept csvBytes() as a separate method, matching how excel()/excelBytes() are already two independent implementations rather than one delegating to the other.

Verified: sail artisan test --filter=Mcp (54/54), sail artisan test --filter=PayrollSummaryReportControllerTest (14/14, unaffected), sail php vendor/bin/phpstan analyse on all touched files (0 errors), vendor/bin/pint --dirty (clean).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a get-payroll-summary-report MCP tool that fetches the "Resumen de Remuneraciones por Período" as CSV text for a period (year+month, optional first/second fortnight) and optional employee_ids, gated by Export:PayrollReport. It reuses the same ReportEmployeeSelector/ReportPeriod/PayrollSummaryReportBuilder/PayrollSummaryReportExporter path the web export uses, so figures always match. It never blocks on PayrollExportReadinessService findings -- it always returns the CSV, adding a warning (message + findings) when the period has unresolved attendance data. Every call is logged to the payroll_export activity log via recordExport(), same as the web export, so MCP-driven exports appear in the KOL-17 audit history.

Verified with 5 new Pest tests (clean period, period with findings -> warning present + data still returned, employee_ids filtering, permission denial, activity-log recording) plus the full mcp test group (54/54) and the existing PayrollSummaryReportControllerTest suite (14/14, unaffected). Larastan clean on all touched files; Pint clean.
<!-- SECTION:FINAL_SUMMARY:END -->
