---
id: KOL-52
title: 'Alert HR to overtime left pending, so inaction stops being invisible'
status: To Do
assignee: []
created_date: '2026-08-06 02:56'
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
