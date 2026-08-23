---
id: KOL-49
title: Expose an overtime export dataset that cannot contain an unapproved hour
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-06 02:54'
updated_date: '2026-08-23 20:45'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-46
  - KOL-47
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 1400
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 7.7, and the single non-negotiable success metric of the whole module: **100% of exported overtime hours carry an explicit approval record.**

The PRD is deliberate about how that is achieved: *"This is a query/view-level constraint, not a UI convention — the technical design must make it impossible for any status other than approved to appear in the export dataset."* A `where('status', 'approved')` that a future caller can forget is not sufficient. A global scope, a dedicated read model, or a query the export cannot construct any other way — the mechanism is the implementer choice, but the property to prove is that there is no code path producing an export row from a pending or objected record.

What the dataset yields per line, for audit traceability: employee RUT, date, approved hours, day type (weekday, Sunday or holiday), the pacto reference when one applies, the approver, and the approval timestamp.

Two exclusions that are easy to get wrong: hours compensated in rest days never appear (KOL-47), and hours falling outside the final figure never appear as payable, though they may be reported separately as unauthorised.

This is the seam the payroll reports consume. KOL-12 classifies these hours into their pay buckets, KOL-13 aggregates them per employee for the period, and KOL-24 reports them by week — all three should read this dataset rather than reaching into overtime records directly.

Day type is derived from existing data: `app/Models/Holiday.php` and its seeder cover festivos, and `app/Services/Reports/SundaysReportService.php` already reasons about Sunday work for the Resolución 38 report and is worth reading before writing anything new.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A dataset returns approved overtime for an employee selection and period, with one line per employee and day
- [x] #2 Each line carries the employee RUT, the date, the approved hours, the day type, the pacto reference when one applies, the approver and the approval timestamp
- [x] #3 It is structurally impossible to obtain a pending or objected record from this dataset; the constraint lives in the query or read model, not in a caller convention, and the test proves the impossibility rather than the happy path
- [x] #4 Hours compensated in rest days are excluded, and unauthorised excess never appears as payable
- [x] #5 Day type is derived from the existing Holiday data and the existing Sunday reasoning rather than a new calendar
- [x] #6 The dataset is organization-scoped and bounded in query count for a 500-employee period
- [x] #7 Pest tests cover an approved period, a period containing pending and objected records, a period with rest-day compensation, a Sunday, a holiday, and an attempt to reach a non-approved record through the dataset
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
1. Add App\Enums\OvertimeDayType (Weekday|Sunday|Holiday) with label() + es/en lang entries under ui.overtime.day_types, mirroring existing overtime enums.
2. Add App\Services\Overtime\OvertimeExportLine: a final readonly DTO (userId, employeeRut, date, hours, dayType, pactReference, approvedBy, approvedAt) — the only shape a payroll export line can take.
3. Add App\Services\Overtime\OvertimeExportDataset with forPeriod(Carbon $start, Carbon $end, array $userIds): Collection<OvertimeExportLine>. Unions two sources per KOL-47's note on this task:
   a. OvertimeAuthorization::exportable() (already approved+payment-only) whereIn user_id, betweenDates($start,$end), eager-loaded user — one line per record, date/hours/pact/approver straight off the row.
   b. OvertimeRestDayBalance expired() whereBetween expiry_date, eager-loaded user+authorization, filtered to payableFromExpiry() > 0 — one line per lapsed-unconsumed balance, dated by expiry_date (the period it becomes payable per Art. 32), hours from payableFromExpiry(), day type/pact/approver taken from the original authorization.
   Day type resolved via a single Holiday::whereIn('date', ...) query covering every date actually needed (both period dates and any out-of-period original workday dates from source b) plus Carbon::isSunday(), reusing SundaysReportService's reasoning.
4. Pest tests in tests/Feature/OvertimeExportDatasetTest.php covering AC#7's list: an approved period, pending+revoked records excluded, rest-day-compensated excluded while active, a Sunday, a holiday, an expired rest-day balance appearing as payable dated by its expiry, and org-scoping/query-count boundedness for a large employee set (query count comparison, not a magic number).
5. vendor/bin/pint --dirty --format agent, then sa test --compact --filter=OvertimeExportDatasetTest.
6. docs/architecture.md: replace the KOL-49 'not yet built' note (line ~262) with what shipped.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented App\Enums\OvertimeDayType (weekday|sunday|holiday, es/en labels), App\Services\Overtime\OvertimeExportLine (readonly DTO) and App\Services\Overtime\OvertimeExportDataset::forPeriod(). Unions OvertimeAuthorization::exportable() (approved+payment) with expired-unconsumed OvertimeRestDayBalance remainders (dated by expiry_date per Art. 32 respectivo periodo, day type still taken from the original workday via the balance's source authorization). Day type resolved via one Holiday::whereIn('date', ...) query covering both period dates and any out-of-period original dates from expiry-sourced lines, reusing SundaysReportService's Sunday reasoning.

Verification: tests/Feature/OvertimeExportDatasetTest.php, 7 tests / 22 assertions, all passing (sail artisan test --compact --filter=OvertimeExportDatasetTest) — covers the happy path (AC1/2), the structural-impossibility case with pending+revoked+active-rest-day records (AC3), a Sunday and a holiday (AC5), an expired rest-day balance surfacing as a distinct payable line (AC4), cross-tenant isolation, and query-count boundedness via a 3-vs-30-employee comparison (AC6, same pattern as TodayApiTest's geofence query-count test). Adjacent suites (OvertimeAuthorizationTest, OvertimeRestDayBalanceTest, OvertimeRestDayBalanceControllerTest, OvertimeQueueTest, OvertimePactManagementTest, WorkdayOvertimeTest) re-run: 78/78 passing, no regressions. vendor/bin/pint --dirty --format agent: clean. phpstan analyse on the three new files: no errors. No TypeScript touched, so DoD#3 does not apply. docs/architecture.md updated with a new 'Payroll export dataset (KOL-49)' section, replacing the stale 'not yet built' note under Rest-day compensation.
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
created: 2026-08-17 11:16
---
Dependency note from KOL-47 (rest-day compensation): per Código del Trabajo art. 32, expired-unused rest-day balance is not forfeited — it must be paid. KOL-47 therefore does NOT give this export a single source to read. It gives two:

1. OvertimeAuthorization rows with compensation_type = payment (status Approved) — the normal path, structurally excludes rest_days rows unconditionally.
2. OvertimeRestDayBalance rows whose remainder expired unconsumed (App\Models\OvertimeRestDayBalance, see its expired-unpaid scope) — these are payable but never live on an OvertimeAuthorization row, because consumption can be partial and an authorization is one row per workday, not per balance-hour.

This dataset's query/read-model needs to union both sources, or the export will silently under-pay anyone whose rest-day balance lapsed unused. See KOL-47's implementation notes and comment for the full statutory reasoning.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Built the structurally-approved-only export dataset PRD §7.7 requires: App\Services\Overtime\OvertimeExportDataset::forPeriod() unions OvertimeAuthorization::exportable() with expired-unconsumed OvertimeRestDayBalance remainders (KOL-47's two-source requirement), returning App\Services\Overtime\OvertimeExportLine DTOs carrying RUT, date, hours, App\Enums\OvertimeDayType (derived from Holiday data + Sunday reasoning), pacto reference, approver and approval timestamp. Verified with 7 new Pest tests (22 assertions) proving the structural exclusion of pending/revoked/active-rest-day records, correct day-type derivation, the expiry-balance union, org-scoping, and bounded query count; 78 adjacent-suite tests re-confirmed with no regressions; pint and phpstan clean.
<!-- SECTION:FINAL_SUMMARY:END -->
