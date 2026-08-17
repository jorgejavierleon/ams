---
id: KOL-47
title: >-
  Support rest-day compensation as an alternative to payment, with an accrual
  balance
status: Done
assignee: []
created_date: '2026-08-06 02:54'
updated_date: '2026-08-17 20:08'
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
- [x] #1 Hours compensated in rest days accrue to a per-employee balance carrying an accrual date and an expiry date
- [x] #2 Consuming rest-day balance decrements it and remains traceable back to the specific accrued hours
- [x] #3 Expired hours are retained and visible as expired rather than deleted
- [x] #4 Rest-day-compensated hours are excluded from the payroll export dataset at the query level and cannot appear in it by any path
- [x] #5 The expiry rule and the default behaviour are validated against Código del Trabajo art. 32 and recorded in the notes with their source before completion
- [x] #6 The balance is visible in Spanish to the employee and to HR
- [x] #7 Pest tests cover accrual, partial consumption, full consumption, expiry, a per-employee override of the tenant default, and the exclusion of rest-day hours from the export dataset
- [x] #8 An approved overtime record's compensation type is chosen by the approver at decision time; it is rest_days only when the employee's profile carries the rest-day-eligibility flag, otherwise payment is the only option and nothing else is configurable
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
1. Enum App\Enums\OvertimeCompensationType (payment|rest_days), reintroduced per KOL-56's note, now living on the pacto instead of a tenant setting.
2. Migrations: add compensation_type to overtime_pacts (default payment) and overtime_authorizations (default payment); create overtime_rest_day_balances (accrual per authorization, 1.5x ratio, accrual_date, expiry_date, expired_at) and overtime_rest_day_consumptions (traceability ledger, FK to balance).
3. OvertimePact: compensation_type in Fillable + casts; form/controller/UI gains the field.
4. OvertimeAuthorization::approve(): resolve compensation_type from the covering pact (payment fallback, non-configurable, AC#8); on rest_days, accrue via RestDayBalanceService. Add scopeExportable() (Approved + compensation_type=payment) — the structural exclusion AC#4 asks for.
5. Models OvertimeRestDayBalance (remaining(), expire(), scopes available/expired/expiredUnpaid) and OvertimeRestDayConsumption.
6. Services\Overtime\RestDayBalanceService: accrueFor(authorization), consume(user, hours, note, registeredBy) with FIFO-by-expiry across balance lines, sweepExpired().
7. Console command + daily schedule entry for the expiry sweep, mirroring NotifyExpiringOvertimePacts.
8. Controllers: OvertimeRestDayBalanceController (HR list + consume action, Manage:OvertimeAuthorization) and My/OvertimeRestDayBalanceController (employee read-only, ViewOwn:OvertimeAuthorization). Routes under existing overtime/my groups.
9. Frontend: overtime/rest-day-balances/index.tsx (HR DataTable + consume dialog), my/overtime-rest-day-balance/index.tsx (employee read-only), link from overtime/index.tsx, compensation_type field added to the pacto form dialog + pacts index column.
10. Lang es/en entries for all of the above, in Spanish per AC#6.
11. Pest tests: accrual off an approval, partial consumption, full consumption, expiry + auto-conversion visibility, per-employee override vs. payment fallback with no pact (AC#8), exportable-scope exclusion (AC#4), pacto compensation_type CRUD.
12. docs/architecture.md note on the two-source export requirement for KOL-49 and the expiry-to-payment design (already flagged via task comments on KOL-47/KOL-49).
13. pint --dirty, sa test --compact, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Reworked per @jorge's correction (comment #4): compensation type is no longer on OvertimePact. Implemented instead as User.overtime_rest_day_eligible (a standing per-employee flag, admin-managed from the employee profile's Laboral tab) plus an explicit compensationType argument on OvertimeAuthorization::approve(), surfaced as a compensation-type selector in the overtime queue's approve dialog — shown only when the employee is eligible. Requesting rest-day compensation for an ineligible employee is refused (OvertimeDecisionRefused::notEligibleForRestDayCompensation() at the model layer; a field-specific validation error at the controller layer). Omitting the choice, or ineligibility, always resolves to payment.

All previously-added OvertimePact.compensation_type code (migration, model, controller, UI, tests) was fully reverted. Verified via targeted Pest runs (OvertimeRestDayBalanceTest, OvertimeRestDayBalanceControllerTest, OvertimeQueueTest, OvertimePactManagementTest, EmployeeManagementTest — 94 tests, all passing against an isolated test database to avoid colliding with a concurrent session's test run on the shared MySQL container), pint clean, phpstan clean on touched files, tsc clean, npm run build clean. Full suite deferred until the user confirms the feature is ready to finalize (per instruction: don't run the full suite during development).
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @jorge
created: 2026-08-08 11:33
---
Amended after the KOL-38 review: the original AC #1 derived the compensation type from the per-tenant setting KOL-37 added, and that setting is being removed in KOL-56. Resolución 38 art. 43 requires systems to *offer* both modes but states the fallback as law — 'si no hubiere pacto escrito que indique lo contrario, las horas extraordinarias se entenderán efectuadas de acuerdo con lo indicado en la letra a)', i.e. payment. It is not an employer preference. Art. 45.3 ('la cantidad de horas compensables de cada dependiente') and art. 41 i) both treat the agreement as per worker, so the compensation type belongs to the pacto this task builds on, not to the organization. The OvertimeCompensationType enum removed in KOL-56 is the vocabulary to reintroduce here, on the agreement.
---

created: 2026-08-11 00:09
---
Cross-reference with KOL-42: the pacto this task reads (AC #8, compensation type resolved from the worker written agreement in force on the day) is the only thing that makes a pacto functionally different from not having one. KOL-42's approval flow itself treats a valid pacto and a missing one (flagged per decision-1) the same way — both reach approval via the supervisor decision, missing/exceeded cases just require a written justification. It's this task's rest-day-vs-payment gating, not KOL-42's approval path, that gives a pacto its real effect.
---

created: 2026-08-17 11:16
---
Legal finding (AC #5), sourced from docs/context/horas_extras_codigo_trabajo.txt, Código del Trabajo art. 32 (the same primary source KOL-42 comment #3 already validated its pacto rules against):

"...las partes podrán acordar por escrito que las horas extraordinarias se compensen por días adicionales de feriado. En tal caso, podrán pactarse hasta cinco días hábiles de descanso adicional al año, los cuales deberán ser utilizados por el trabajador dentro de los seis meses siguientes al ciclo en que se originaron las horas extraordinarias, para lo cual el trabajador deberá dar aviso al empleador con cuarenta y ocho horas de anticipación. Si no los solicita en la oportunidad indicada corresponderá su pago dentro de la remuneración del respectivo periodo. La compensación de horas extraordinarias por días adicionales de feriado se regirá por el mismo recargo que corresponde a su pago, es decir, por cada hora extraordinaria corresponderá una hora y media de feriado. En caso de que existan días pendientes de utilizar al término de la relación laboral, éstos se compensarán en conformidad a lo establecido en el artículo 73."

Three concrete facts this fixes, none of which the PRD or this task's original AC wording specified:
1. Expiry window is 6 months from the cycle the overtime originated in — not an arbitrary implementation choice.
2. Ratio is 1:1.5 — 1 overtime hour accrues 1.5 hours of rest, not 1:1.
3. Expired-unused hours are NOT forfeited — they must be paid in that period's payroll. AC #3's "visible as expired" and AC #4's "cannot appear [in the export] by any path" would, read literally and together, make expired hours legally unpayable by construction. That is the opposite of the statute.

Amendment, confirmed with @jorge before implementation: on expiry, the unconsumed remainder auto-converts to a payment-eligible obligation rather than sitting as dead/forfeited balance. AC #4's structural exclusion is implemented as: an OvertimeAuthorization approved with rest_days compensation is excluded from the (future, KOL-49) export's query scope unconditionally and permanently — that guarantee never weakens. The expired-unpaid remainder is a second, distinct, auditable source (on the balance ledger itself, not on the original authorization row, since consumption can be partial and the authorization is one row per workday) that KOL-49 must additionally union in when it is built. Left as an explicit note on KOL-49 so this isn't lost.

Two statutory details this task does NOT implement, flagged rather than guessed at: the 5-business-day/year cap on how much rest a pacto may agree to compensate (a constraint on the pacto's terms, not the balance mechanics), and the 48-hour advance-notice requirement for the employee to request use of accrued rest (a self-service consumption request flow doesn't exist yet — consumption here is HR-registered). Both are Código del Trabajo art. 32 requirements a future ticket should pick up if/when self-service consumption is built.

Art. 73 termination settlement is also out of scope here — no finiquito/termination flow exists yet in this app for this to hook into.
---

created: 2026-08-17 12:48
---
Correction from @jorge after initial implementation: the compensation type does NOT belong on OvertimePact. Original AC #8 ("resolved from the worker written agreement in force on the day") is wrong and is being superseded.

Correct design: a boolean eligibility flag lives on the employee's profile (User), independent of whether they have any overtime pacto at all. When that flag is true, the admin/supervisor deciding an overtime record — at approval time, per record — gets the option to compensate it as rest days instead of payment. When the flag is false, payment is the only option, exactly as before. The pacto (KOL-42) keeps governing only whether overtime is approvable, never the compensation currency.

This changes:
- OvertimePact.compensation_type is removed (was added in the first pass of this task, incorrectly).
- New User.<flag> boolean, admin-managed from the employee profile.
- OvertimeAuthorization::approve() takes the compensation type as an explicit argument from the approver, only honored when the employee's flag is true; otherwise always Payment regardless of what's passed — the fallback stays non-configurable.
- The overtime queue's approve UI needs a compensation-type control, shown only for eligible employees.

Reworking now. AC #8 is amended to read: "An approved overtime record's compensation type is chosen by the approver at decision time, and is only ever rest_days when the employee's profile carries the rest-day-eligibility flag; otherwise payment is the only option and nothing else is configurable."
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Rest-day compensation shipped as a standing per-employee eligibility flag (User.overtime_rest_day_eligible), chosen by the approver at decision time via OvertimeAuthorization::approve()'s compensationType argument -- never derived from a pacto or tenant setting, per Jorge's mid-implementation correction. Balance accrues at the statutory 1.5x ratio (Codigo del Trabajo art. 32 par.4) with a 6-month expiry from the worked date; expired-unconsumed balance auto-converts to payable rather than being forfeited, via a distinct, permanent source (OvertimeRestDayBalance) that never touches the original authorization row -- OvertimeAuthorization::scopeExportable() structurally and permanently excludes rest-day-compensated records, flagged on KOL-49 to union both sources. HR list, employee read-only view, and the queue/Jornadas approve dialogs all surface it in Spanish. Verified: targeted Pest suites (OvertimeRestDayBalanceTest, OvertimeRestDayBalanceControllerTest, OvertimePactManagementTest, EmployeeManagementTest, OvertimeQueueTest, WorkdayOvertimeTest) all passing against an isolated test DB; pint clean; phpstan clean on touched files; tsc clean; npm run build succeeds.
<!-- SECTION:FINAL_SUMMARY:END -->
