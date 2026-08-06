---
id: KOL-48
title: Mail accrued rest-day hours and their expiry every 30 days (Res. 38 art. 45.3)
status: To Do
assignee: []
created_date: '2026-08-06 02:54'
labels:
  - overtime
  - backend
  - compliance
milestone: m-2
dependencies:
  - KOL-47
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: medium
type: feature
ordinal: 1300
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Resolución 38 art. 45.3: when rest-day compensation has been agreed, the system must send an automatic email **every 30 days** showing the accrued hours and the date on which they expire. This is a hard compliance requirement, not a convenience feature — it is the mechanism by which the worker learns that time off they earned is about to lapse.

A scheduled command walks the employees carrying a rest-day balance and mails each their current accrued hours and expiry dates. Employees with no balance get nothing. The 30-day cadence is per employee from their last notification, so someone who starts accruing mid-month is not silently skipped until the next global run.

Follow the existing patterns rather than new ones: scheduled commands in `routes/console.php` alongside `mark-modifications:approve-overdue`, mailables and their Spanish content under `lang/es/mail.php`, and the per-organization notification toggles on `app/Models/Setting.php` if the tenant should be able to control it — though note that a legally mandated notification is a poor candidate for a toggle, so if one is added, document why.

Sending must be idempotent under retry: a queue retry or a re-run of the command on the same day must not mail the same employee twice.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A scheduled command mails every employee carrying a rest-day balance their accrued hours and the expiry date of each accrual
- [ ] #2 The cadence is 30 days measured per employee from their own last notification, not from a global run date
- [ ] #3 Employees with no rest-day balance are not mailed
- [ ] #4 A retry or a same-day re-run of the command does not mail the same employee twice
- [ ] #5 The email content is in Spanish and lives in the existing translation files
- [ ] #6 If the notification is made toggleable per tenant, the reasoning for allowing a legally mandated notification to be disabled is recorded in the notes
- [ ] #7 Pest tests cover an employee with a balance, one without, the 30-day boundary either side, and idempotency on a repeat run
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
