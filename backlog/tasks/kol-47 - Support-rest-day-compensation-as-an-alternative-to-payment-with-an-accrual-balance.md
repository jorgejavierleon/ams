---
id: KOL-47
title: >-
  Support rest-day compensation as an alternative to payment, with an accrual
  balance
status: To Do
assignee: []
created_date: '2026-08-06 02:54'
updated_date: '2026-08-06 02:56'
labels:
  - overtime
  - backend
  - frontend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-46
  - KOL-37
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: medium
type: feature
ordinal: 1200
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Resolución 38 art. 43 requires the system to offer **two** compensation modes for approved overtime — payment in the payroll run, or additional rest days — and, absent a written agreement stating otherwise, payment is assumed. Kolvi currently offers only the first, implicitly.

When a tenant or an individual agreement elects rest-day compensation, the approved hours must not flow to the payroll export at all. They accrue as a balance instead, with an expiry date, and the export must be structurally unable to pick them up — otherwise the client pays for the same hours twice, once in cash and once in time off.

What lands here:
- An approved overtime record carries its compensation type, defaulting from the tenant setting in KOL-37 and overridable per agreement or per employee where a written agreement says so.
- Hours compensated in rest days accrue to a per-employee balance with an accrual date and an expiry date.
- Consuming the balance decrements it, and the consumption is traceable back to the specific accrued hours.
- Expired hours are visible as expired rather than deleted — the record of what was accrued and lapsed is exactly what an audit asks for.
- The payroll export from KOL-49 reads only payment-compensated hours; rest-day hours are excluded at the query level, not filtered in the UI.

The PRD closes with a recommendation worth respecting: validate the default behaviour around rest-day compensation with a labor law advisor before finalising, particularly the expiry rule. Record the finding and its source in the notes before this is considered done.

Reference: docs/PRD_Overtime_Module_Kolvi_EN.md sections 5, 10 and the closing note. Código del Trabajo art. 32 paragraph 4.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 An approved overtime record carries a compensation type of payment or rest days, defaulting from the tenant setting and overridable where a written agreement says so
- [ ] #2 Hours compensated in rest days accrue to a per-employee balance carrying an accrual date and an expiry date
- [ ] #3 Consuming rest-day balance decrements it and remains traceable back to the specific accrued hours
- [ ] #4 Expired hours are retained and visible as expired rather than deleted
- [ ] #5 Rest-day-compensated hours are excluded from the payroll export dataset at the query level and cannot appear in it by any path
- [ ] #6 The expiry rule and the default behaviour are validated against Código del Trabajo art. 32 and recorded in the notes with their source before completion
- [ ] #7 The balance is visible in Spanish to the employee and to HR
- [ ] #8 Pest tests cover accrual, partial consumption, full consumption, expiry, a per-employee override of the tenant default, and the exclusion of rest-day hours from the export dataset
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
