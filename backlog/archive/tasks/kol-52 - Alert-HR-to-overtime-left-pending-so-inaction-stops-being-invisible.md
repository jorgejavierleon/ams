---
id: KOL-52
title: 'Alert HR to overtime left pending, so inaction stops being invisible'
status: To Do
assignee:
  - '@jorge'
created_date: '2026-08-06 02:56'
updated_date: '2026-09-11 09:12'
labels:
  - overtime
  - backend
  - frontend
  - compliance
milestone: m-2
dependencies:
  - KOL-44
  - KOL-37
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: medium
type: feature
ordinal: 1700
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
This closes the open risk the PRD raises in section 12, and it is a legal exposure for the client rather than a nice-to-have.

The module refuses to auto-approve anything: a record nobody acts on stays pending forever and is simply never exported. That is correct as a payment rule, but it is not the end of the story legally. The Dirección del Trabajo applies a *criterio de realidad* — under Código del Trabajo art. 32, hours worked with the employer knowledge can carry a payment obligation even without a written authorisation. So a shift excess that sits in the queue untouched for two months is not a neutral non-event; it is the employer having known and done nothing, which is the worst of both worlds: unpaid to the worker and indefensible in an inspection.

The mitigation the PRD proposes is an alert to HR when records stay pending past a configurable number of days, so inaction becomes visible rather than silent. That is what this task builds:
- A report of overtime pending beyond the threshold, grouped by employee and supervisor, so it is obvious *who* is not acting rather than only that something is stale.
- A periodic notification to HR while anything is over the threshold.
- The threshold configurable per tenant alongside the other policy settings from KOL-37.

The second success metric of the PRD, average time between mark and anomaly resolution, is measurable from the same data, so surface it here rather than building a second aggregation later.

Follow the existing scheduled-command and mailable patterns, and put the report on the shared DataTable foundation. Spanish throughout.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A report lists overtime records pending beyond the configured threshold, grouped so the responsible supervisor is identifiable, not only the affected employee
- [ ] #2 HR is notified periodically while records remain over the threshold, and not notified when nothing is stale
- [ ] #3 The threshold is a per-tenant setting alongside the other overtime policy values
- [ ] #4 The average time between the marked day and its resolution is surfaced from the same data
- [ ] #5 The report is in Spanish, uses the shared DataTable foundation, and links to the queue where each record is resolved
- [ ] #6 The report is organization-scoped and bounded in query count for a large organization
- [ ] #7 Pest tests cover records inside and outside the threshold, an organization with nothing stale receiving no notification, and the resolution-time figure
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
1. Decision, since this ticket predates KOL-80: KOL-80 removed persisted 'pending' overtime rows — a day needing a decision is now a Workday with calculated_overtime > 0 and no *approved* OvertimeAuthorization (no row at all, or a leftover pending/revoked one). 'Pending beyond threshold' is therefore computed on Workday.date, not on an OvertimeAuthorization row's created_at.
2. Setting (KOL-37 extension): migration adds overtime_pending_alert_threshold_days (unsignedSmallInteger, default 15 — no legal figure given, same kind of judgment call as the retroactive-window default). Setting model: fillable/$attributes/casts. OrganizationSettings::overtimePendingAlertThresholdDays(). SettingController + organization-settings.tsx: new field in the existing 'Horas extra' section. Extend OrganizationSettingsTest.php (defaults, round-trip, validation, Spanish labels, tenant isolation) rather than a new file.
3. Workday model: add scopeNeedsOvertimeDecision() (calculated_overtime > 0 and whereDoesntHave('overtimeAuthorization', approved())) — the query form of WorkdayController::overtimeRowData()'s not_opened/pending case.
4. New app/Services/Overtime/OvertimePendingReport.php: staleWorkdaysQuery(thresholdDays) (needsOvertimeDecision + date <= today-threshold, eager-loads user + user.supervisor), staleCount(), averageResolutionDays() (single AVG(DATEDIFF(reviewed_at, date)) aggregate over approved records — bounded, no per-row loop).
5. New app/Http/Controllers/OvertimePendingReportController.php: index() paginates staleWorkdaysQuery via the DataTable foundation, gated by Manage:OvertimeAuthorization; exposes threshold + averageResolutionDays as report stats and a link to workdays.show per row.
6. Route overtime/pending (name overtime.pending.index) under the existing Manage:OvertimeAuthorization group; wayfinder regenerate. OvertimeController::index + overtime/index.tsx: add a 'viewPendingAlerts' hub link (four buttons now).
7. resources/js/pages/overtime/pending/index.tsx: DataTable page (employee, supervisor, date, days pending, calculated hours, link-to-Jornadas action), stat tiles for stale count / threshold / average resolution days. Spanish/English lang additions (ui.overtime.pending.*, organization_settings field).
8. Notification: app/Notifications/OvertimePendingOvertimeAlert.php (ShouldQueue, markdown mail) + resources/views/mail/overtime/pending-alert.blade.php + lang/{es,en}/mail.php entries — digest (stale count, oldest days pending, link to the report), sent to every Manage:OvertimeAuthorization holder in the organization.
9. app/Services/Overtime/OvertimePendingAlertNotifier.php: loops Organization::withoutGlobalScopes() ids via CurrentOrganization::runAs(), reads that org's threshold, sends the digest only when staleCount() > 0 (nothing sent otherwise) — no per-record dedup, this is a recurring reminder by design (PRD: 'periodic notification while anything is over the threshold').
10. app/Console/Commands/NotifyPendingOvertime.php (signature overtime:pending:notify-stale) + routes/console.php Schedule::command(...)->dailyAt('07:45') alongside the other daily overtime jobs.
11. Pest: tests/Feature/OvertimePendingReportTest.php (records inside/outside threshold, excludes approved, includes revoked-but-not-reapproved, org scoping, average-resolution figure, permission gate, bounded query count) and tests/Feature/OvertimePendingAlertNotifierTest.php (notifies when stale exists, silent when nothing stale, per-org threshold isolation, recipients scoped to Manage:OvertimeAuthorization holders in that org only).
12. vendor/bin/pint --dirty, targeted Pest subset, npm run types:check / build (Wayfinder).
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented against the current (post-KOL-80) model, not the ticket's original conception: since KOL-80, nothing creates a persisted 'pending' OvertimeAuthorization row ahead of a decision — a day needing review is simply a Workday with calculated_overtime > 0 and no *approved* row (no row at all, a leftover pending one from a failed attempt, or a revoked one). The report and notifier are built on that read-only condition (Workday::scopeNeedsOvertimeDecision()), anchored on the worked Workday.date rather than any authorization timestamp.

Backend: overtime_pending_alert_threshold_days added to Setting (KOL-37 extension, default 15 — no legal figure exists, same kind of judgment call as the retroactive-request-window default). app/Services/Overtime/OvertimePendingReport.php provides staleWorkdaysQuery()/staleCount()/oldestDaysPending()/averageResolutionDays() (a single AVG(DATEDIFF(reviewed_at, date)) aggregate, bounded regardless of row count). app/Http/Controllers/OvertimePendingReportController.php serves overtime/pending on the shared DataTable foundation, gated by Manage:OvertimeAuthorization.

Notification: app/Services/Overtime/OvertimePendingAlertNotifier.php loops every organization via CurrentOrganization::runAs(), and — unlike the one-shot OvertimePactNearingExpiry — has no per-record dedup by design: it re-sends every run while any record stays over that org's threshold, and sends nothing when an org has none. Scheduled daily at 07:45 (overtime:pending:notify-stale).

Verified in the browser against real dev data (admin@example.com, org 1): 8 real seeded workdays with tiny unauthorized overtime from 2026-08-26 correctly surfaced as stale at the 15-day default threshold, with working Employee/Supervisor/Fecha/Días pendiente/Horas calculadas columns and a 'Resolver' link that lands on the correct Jornada detail page's Aprobar action. The average-resolution stat (28.1 días) is computed live from real approved records in that DB, not fixture data. Settings screen shows the new field correctly in the existing 'Horas extra' section.

Discovered along the way (not fixed, out of scope): the sidebar's pre-existing 'Horas extra pendientes' nav entry actually points to /overtime/requests (Mode A Solicitudes review), not to anything about stale pending decisions — looks like a stale label left over from before KOL-72 split Solicitudes out. To avoid confusion with this ticket's new report, titled the new page/hub-button 'Alerta de horas extra pendientes' instead of reusing that exact phrase. Flagging the sidebar mislabel to the user; did not touch it.

Tests: tests/Feature/OvertimePendingReportTest.php (10 tests) and tests/Feature/OvertimePendingAlertNotifierTest.php (5 tests), plus extended tests/Feature/OrganizationSettingsTest.php for the new setting. Targeted suite run: 341 passed (Overtime|Workday|OrganizationSettings filter) — full suite deferred per standing instruction not to run it mid-development. pint clean, phpstan clean (0 errors) on every touched non-test file, npm run types:check clean on every file this ticket touched (two pre-existing unrelated failures in resources/js/pages/roles/{index,show}.tsx predate this branch and were left untouched), npm run build succeeds and produces the new page bundle.

Code review (fork) surfaced three real findings, all fixed:
1. stats.stale_count used an unfiltered staleCount() while the table respected the search filter — could show '8' next to a search result of 1 row. Now uses the paginator's own total() so both agree, and drops the redundant COUNT query.
2. OvertimePendingAlertNotifier called staleCount()+oldestDaysPending() separately (2 queries) per organization per run. Added OvertimePendingReport::staleSummary() (one COUNT+MIN aggregate) and switched the notifier to it.
3. Workday::scopeNeedsOvertimeDecision() re-implemented the can_decide condition that WorkdayController::overtimeRowData() and WorkdayPresenter::overtime()/overtimeTimelineEntry() computed inline (! $isApproved) — two parallel definitions of the same rule with no shared source. Added Workday::needsOvertimeDecision() as the single canonical instance definition (mirroring the existing overtimeNeedsReReview()/scopeNeedsOvertimeReReview() instance+query-form pattern already in this file) and pointed all three call sites and the scope's docblock at it.

Added one regression test (stale-count stat agrees with an active search filter) for fix #1. Re-verified after fixes: 342 tests passing in the targeted overtime/workday/settings suite, pint clean, phpstan 0 errors on every touched file.
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @claude
created: 2026-09-11 09:12
---
Reverted per @jorge: the report + daily-digest notification "added too much noise and not real value." Full implementation (report, notifier, per-tenant threshold setting, hub link, migration, tests) removed in commit dec2330/8d051a9. The unrelated sidebar nav-label fix discovered along the way (overtime.requests mislabeled "Horas extra pendientes") was kept.

Implementation Notes and Final Summary below are left as a historical record of what was built and how it was verified, in case a differently-scoped approach to PRD §12's underlying risk is revisited later — they no longer describe code that exists in the app.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Built the PRD §12 stale-overtime alert: a per-tenant threshold (overtime_pending_alert_threshold_days, default 15, extending KOL-37's settings), a DataTable-based report at /overtime/pending listing every shift excess with no approved decision past that threshold — employee, supervisor, date, days pending, calculated hours, and a link straight to the Jornadas day where it's resolved — plus the average marked-day-to-resolution figure computed from the same data. A daily command (overtime:pending:notify-stale) mails everyone who manages overtime for an organization whenever it has anything stale, and stays silent otherwise; unlike the pacto-expiry alert it deliberately re-sends every run for as long as the condition holds.

Built against the current, post-KOL-80 model rather than the ticket's original framing: 'pending' is no longer a persisted row state, so both the report and the notifier are derived from Workday.calculated_overtime + the absence of an approved OvertimeAuthorization, anchored on the worked date.

Verified: 15 new Pest tests (OvertimePendingReportTest, OvertimePendingAlertNotifierTest) plus an extended OrganizationSettingsTest, 341 passing in the targeted overtime/workday/settings suite; pint and phpstan clean; npm run types:check and build clean. Also manually verified end-to-end in the browser against real dev data — the report surfaced real stale records, the resolve link landed on the correct approve action, and the settings field rendered correctly.
<!-- SECTION:FINAL_SUMMARY:END -->
