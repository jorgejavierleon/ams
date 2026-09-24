---
id: KOL-129
title: Add an MCP tool to fetch the payroll summary report as CSV
status: To Do
assignee: []
created_date: '2026-09-24 19:32'
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
- [ ] #1 The tool returns the summary report's data as CSV text for a given period (and optional employee filter), matching the web export's figures exactly
- [ ] #2 The tool never blocks on readiness findings; it always returns data, surfacing findings as a warning when present
- [ ] #3 The tool sends no email and writes no file to disk
- [ ] #4 Gated by the existing Export:PayrollReport permission
- [ ] #5 Pest tests cover: a clean period, a period with findings (warning present, data still returned), and denial for a user without the permission
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
