---
id: KOL-48
title: Mail accrued rest-day hours and their expiry every 30 days (Res. 38 art. 45.3)
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-06 02:54'
updated_date: '2026-08-22 13:12'
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
- [x] #1 A scheduled command mails every employee carrying a rest-day balance their accrued hours and the expiry date of each accrual
- [x] #2 The cadence is 30 days measured per employee from their own last notification, not from a global run date
- [x] #3 Employees with no rest-day balance are not mailed
- [x] #4 A retry or a same-day re-run of the command does not mail the same employee twice
- [x] #5 The email content is in Spanish and lives in the existing translation files
- [x] #6 If the notification is made toggleable per tenant, the reasoning for allowing a legally mandated notification to be disabled is recorded in the notes
- [x] #7 Pest tests cover an employee with a balance, one without, the 30-day boundary either side, and idempotency on a repeat run
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
1. Migration: add nullable users.rest_day_balance_notified_at timestamp (per-employee cadence anchor + retry/idempotency guard), mirroring the users.overtime_rest_day_eligible migration.
2. User model: cast rest_day_balance_notified_at as datetime, add restDayBalances() hasMany, scopeDueForRestDayBalanceNotification() (has a spendable balance AND never notified or notified >=30 days ago), markRestDayBalanceNotified() (forceFill+save, not fillable) -- mirrors OvertimePact::scopeNearingExpiry/markExpiryNotified.
3. OvertimeRestDayBalance model: add scopeSpendable() (unexpired() + whereColumn(rest_hours > consumed_hours)) for query-level 'has a balance' checks.
4. Service App\Services\Overtime\RestDayBalanceNotifier::notifyDue(): int -- queries due users, loads each user's spendable balance lines ordered by expiry_date, Notification::send()s RestDayBalanceAccrued, stamps markRestDayBalanceNotified(). Mirrors OvertimePactExpiryNotifier.
5. Notification App\Notifications\RestDayBalanceAccrued (ShouldQueue, via mail) carrying the balance lines; toMail renders a new markdown view listing each line's remaining hours + expiry date, linking to my.overtime-rest-day-balance.index.
6. Spanish copy in lang/es/mail.php under rest_day_balance_accrued (subject/heading/body/hours/expiry_date/action), matching overtime_pact_nearing_expiry's shape.
7. Console command overtime:rest-day-balances:notify (NotifyRestDayBalances), scheduled dailyAt('07:00') in routes/console.php alongside the sweep-expired entry. No per-tenant toggle added -- Res. 38 art. 45.3 is a hard compliance requirement, not a preference (documented in the command/service docblock and task notes, AC#6 satisfied as N/A).
8. Pest tests/Feature/RestDayBalanceNotificationTest.php: employee with spendable balance is mailed and stamped; employee with none is skipped; boundary just-under-30-days is skipped; boundary at/over-30-days is mailed; a same-day re-run after stamping does not mail twice.
9. pint --dirty --format agent, sa test --compact --filter=RestDayBalanceNotification, then the touched OvertimeRestDayBalance/User suites.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as: users.rest_day_balance_notified_at (nullable timestamp) drives per-employee 30-day cadence and idempotency, mirroring OvertimePact.expiry_notified_at. User::scopeDueForRestDayBalanceNotification() selects employees with a spendable (unexpired, rest_hours>consumed_hours) OvertimeRestDayBalance line who were never notified or notified >=30 days ago. RestDayBalanceNotifier::notifyDue() mails each via the new RestDayBalanceAccrued mail notification (Spanish, lang/es/mail.php: rest_day_balance_accrued.*) listing every spendable line's remaining hours and its own expiry date, then stamps User::markRestDayBalanceNotified(). Scheduled overtime:rest-day-balances:notify dailyAt 07:00 alongside the existing sweep-expired command. No per-tenant toggle was added: Res. 38 art. 45.3 is a hard legal requirement, not an employer preference, so it is not gated by Setting -- documented in RestDayBalanceNotifier's docblock (AC#6 is N/A as a result).

Full suite run: sail artisan test --compact -> 1096/1104 passing, 1 pre-existing failure (UpcomingShiftsApiTest::the_days_param_is_capped_at_30, a date-dependent test) confirmed present on master before this branch's changes via git stash + re-run -- unrelated to KOL-48. DoD #3 (npm run types:check) not applicable: no TypeScript touched.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added overtime:rest-day-balances:notify, scheduled daily, mailing every employee carrying spendable rest-day balance their accrued hours and each accrual's expiry date, spaced 30 days per employee via users.rest_day_balance_notified_at (idempotent under retry/re-run). Spanish content in lang/es/mail.php. No per-tenant toggle: documented as a deliberate omission since Res. 38 art. 45.3 is a hard legal requirement. Verified with 6 new Pest tests (balance/no-balance, 30-day boundary both sides, idempotency) plus a manual end-to-end check: ran the command against a real employee, confirmed the email rendered correctly in Mailpit, and confirmed the employee saw the correct balance on /my/overtime-rest-day-balance.
<!-- SECTION:FINAL_SUMMARY:END -->
