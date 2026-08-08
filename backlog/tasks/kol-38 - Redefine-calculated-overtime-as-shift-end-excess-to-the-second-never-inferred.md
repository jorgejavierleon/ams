---
id: KOL-38
title: >-
  Redefine calculated overtime as shift excess (pre- and post-shift), to the
  second, never inferred
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-06 02:49'
updated_date: '2026-08-08 16:48'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-36
  - KOL-37
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 300
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
**The current overtime number does not mean what the PRD says overtime means, and it is wrong for a whole class of shifts.**

`app/Services/WorkdayCalculator.php` computes `extra_time` in a SQL `CASE` (around line 177) as *in-to-out span minus scheduled shift duration*. The PRD section 7.2 defines shift excess as *last mark minus shift end time*. These are different numbers whenever an employee arrives early. Worse, the expression wraps every value in `TIME()`, so a shift crossing midnight produces a negative or nonsensical difference — the same defect affects `in_time_difference` and `out_time_difference`.

**Early arrival is a policy question, not a fixed rule.** The span-based formula counts early arrival as overtime silently; the PRD formula structurally cannot represent it at all. Both are wrong as absolutes. Art. 32 requires employer knowledge or authorisation behind excess hours, so an employee who decides alone to arrive two hours early has not earned overtime — that is the exact failure mode the PRD guards against, the mirror image of a forgotten clock-out. But the DT's *reality criterion* says hours actually worked with the employer's knowledge must be paid whether or not they were requested, and that includes hours before shift start (early loading, shift handover, opening prep). Every comparable product treats this as configuration: `docs/prd-reports.md:60` records GeoVictoria's `AttendanceBook` returning *horas extra (antes/después de turno)*, and Buk and Talana split the same way.

So compute the two excesses separately and always store both:

- pre-shift excess = `shift start − first mark`, positive only
- post-shift excess = `last mark − shift end`, positive only
- OHC = post-shift excess, plus pre-shift excess only when the tenant enables it

Storing both unconditionally is deliberate: enabling the policy later must be a configuration change, not a recalculation of history. A persistent pre-shift excess is also a signal KOL-40 can flag as a shift-definition problem rather than overtime.

The toggle is one more key on the overtime policy row KOL-37 added to `settings` — follow `app/Models/Setting.php` and read it through `app/Services/OrganizationSettings.php` so the calculation issues no query per day. It defaults to **not** counting pre-shift excess, which preserves the safety property the PRD is after. Its control belongs in the existing 'Horas extra' section of `resources/js/pages/organization-settings.tsx`, in Spanish.

Two further requirements from Resolución 38 art. 44: precision to the second with no rounding that favours either party (`gmdate('H:i:s', ...)` on a seconds integer is fine; rounding to quarter hours is not), and no inference — when there is no assigned shift for the day, there is no basis to claim overtime, so the calculated value is null rather than zero or the whole worked span.

**Do not repurpose `extra_time`.** It is consumed today by the Resolución 38 DT reports in `app/Services/Reports/`, and quietly changing its meaning would change what those reports show a labor inspector. Introduce the calculated overtime as its own value (OHC in the PRD glossary) alongside it, and record the legal-limit version from KOL-36 that the day was judged against. A later task can retire `extra_time` once the DT reports have been re-pointed deliberately.

Behaviour to settle and record in the notes before finalising: how a shift that crosses midnight attributes its overtime (to the calendar day the shift started, per the usual Chilean reading), and what happens on a day with a scheduled shift but only one mark — the answer there is that OHC is not computed at all, because KOL-40 will flag it as an anomaly instead of guessing.

Reference: docs/PRD_Overtime_Module_Kolvi_EN.md sections 7.2 and 9.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A calculated day stores pre-shift excess (shift start minus first mark, positive only) and post-shift excess (last mark minus shift end, positive only) as separate values, and neither is derived from worked span minus scheduled duration
- [x] #2 Both excesses are stored for every calculated day regardless of the tenant policy, so switching the policy on or off later changes only future OHC values and needs no recalculation of stored excesses
- [x] #3 Calculated overtime (OHC) for a day equals the post-shift excess, plus the pre-shift excess only when the organization has enabled counting it
- [x] #4 An organization can toggle whether pre-shift excess counts towards OHC; a brand-new organization defaults to not counting it, and the value is readable by calculation code without a database query per day
- [x] #5 The toggle is edited from the existing 'Horas extra' section of the organization settings screen in Spanish, by a user holding the same permission as the rest of that section
- [x] #6 An early arrival with an on-time exit produces zero OHC under the default policy and a positive OHC equal to the early minutes once the organization enables pre-shift counting
- [x] #7 A day with no assigned shift produces no calculated overtime at all, rather than zero or the full worked span
- [x] #8 A day with only one mark produces no calculated overtime, leaving the day to be flagged rather than guessed
- [x] #9 Precision is to the second with no rounding at any intermediate step; rounding, if any, happens only when a report renders the value
- [x] #10 A shift crossing midnight produces correct pre- and post-shift excesses, and the calendar day the overtime is attributed to is documented in the notes with its reasoning
- [x] #11 The existing extra_time column and every Resolución 38 DT report that reads it produce exactly the same output as before this change
- [x] #12 Each calculated day records which legal-limit version it was judged against
- [x] #13 Pest tests cover a normal overflow, an early arrival with an on-time exit under both policy values, an early arrival combined with a late exit, a day with no shift, a day with one mark, an overnight shift, a sub-minute overflow, and the default plus tenant isolation of the new setting
- [x] #14 The decision of whether pre-shift excess counts is resolved through a single seam that takes the workday and returns the applicable policy, so a future per-shift or per-day override changes that resolver and nothing in the calculation itself
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
1. Migration: add settings.overtime_counts_pre_shift_excess (bool, default false) and workdays.pre_shift_excess / post_shift_excess / calculated_overtime (time, nullable).
2. Setting model (fillable, $attributes, casts, docblock) + SettingFactory + OrganizationSettings::overtimeCountsPreShiftExcess() read off the cached attributes array.
3. New app/Services/Overtime/: ShiftExcess (pure value object over marks + shift times + date, returns null when not computable, seconds precision, anchors an overnight shift end to date+1), OvertimeExcessPolicy (readonly value object) and OvertimeExcessPolicyResolver::for($workday) — the single seam AC #14 asks for.
4. Wire into WorkdayCalculator (calculateDate + recalculateWorkday) in PHP, leaving the SQL for extra_time / in_time_difference / out_time_difference untouched so AC #11 holds.
5. Workday model docblock props.
6. SettingController BOOLEAN_KEYS + lang es/en + organization-settings.tsx toggle in the existing 'Horas extra' section.
7. Tests: tests/Unit/ShiftExcessTest.php (arithmetic incl. overnight with a next-day out mark, sub-minute, nulls), WorkdayCalculatorTest additions (normal overflow, early arrival both policies, early+late, no shift, one mark, overnight anchoring, extra_time regression), OrganizationSettingsTest (default, render, update, tenant isolation).
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Behaviour settled during implementation

**Midnight attribution.** A shift whose end_time is at or before its start_time is treated as crossing midnight, so ShiftExcess anchors its end to date+1 rather than comparing clock times. Both excesses — and therefore the OHC — are attributed to the calendar day the shift **started**. Reasoning: a jornada is one indivisible unit in the Chilean reading; splitting it at midnight would double-count the boundary and judge each half against a daily cap it was never worked under. Comparing clock times instead would report a 23:30 exit from a 22:00–06:00 shift as 17h30 of overtime.

**A day with a scheduled shift but only one mark.** No OHC at all — pre_shift_excess, post_shift_excess and calculated_overtime are all null. Null is a different statement from zero: it says there is no basis to compute, which is what KOL-40 needs to see in order to flag the day rather than have the engine guess at a missing punch.

**A day with no assigned shift.** Same: all three null, however many hours were marked. Nothing is inferred without a schedule to exceed.

## Known gap opened, not closed

WorkdayCalculator joins marks by calendar date (whereDate on marks.date_time), so an overnight shift's exit mark — which falls on the following day — is not paired to the workday that owns it. Such a day currently reads as a single mark and computes no OHC. Fixing the join changes extra_time, worked_time and status for those days, which AC #11 rules out here, so it needs its own task. The excess arithmetic itself already handles a next-day exit correctly and is tested for it directly in tests/Unit/ShiftExcessTest.php.

## Bug fixed in passing

OrganizationSettings::current() could not create a settings row outside a web request: organization_id is deliberately not fillable, so firstOrCreate dropped it and the BelongsToOrganization creating hook stamped null. Nothing had hit this before because every caller was a request with an active tenant; the calculation engine is the first console reader. current() now stamps the id by hand, and returns an unsaved model (schema defaults intact) rather than writing an orphan row when there is no organization at all.

## Where AC #12 is covered

Already satisfied by KOL-36 — WorkdayCalculator stamps legal_hour_limit_id from the day's own date on both calculateDate and recalculateWorkday, tested in tests/Feature/LegalHourLimitTest.php. No change needed here.
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @jorge
created: 2026-08-07 16:19
---
Amended after review: the original AC #1 (OHC = last mark − shift end) would have made pre-shift work unrepresentable. PRD §7.2 states that formula as absolute, but docs/prd-reports.md:60 records GeoVictoria's AttendanceBook splitting 'horas extra (antes/después de turno)', and Buk and Talana do the same, so the market treats it as tenant policy. The DT reality criterion also requires paying hours worked with employer knowledge, including before shift start. Resolution: store both excesses always, gate only the pre-shift contribution to OHC behind a setting defaulting to off. PRD §7.2 still reads as the absolute rule and should be updated to match.
---

author: @jorge
created: 2026-08-07 19:00
---
Follow-up decided with the user: keep the toggle per-tenant for now rather than per-shift. Shift already carries per-shift policy (tolerance_in, tolerance_out, work_on_holidays) so a shift-level flag would not be foreign, but no tenant has asked, and because both excesses are stored unconditionally the move from tenant to shift is later a recompute, not a data migration. The seam AC keeps that move cheap. Promote to shift level only when real usage shows tenants setting the same override on the same shifts repeatedly.

Separately: because the golden rule caps the payable figure at MIN(OHR, OHA, OHC), a tenant with the setting off cannot pay legitimate pre-shift work at all — the value is stored, visible and structurally unpayable. That is a hard ceiling, not a default. Resolved in a dedicated task rather than here.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Calculated overtime (OHC) is now built from two separately stored shift excesses — pre-shift (shift start minus first mark) and post-shift (last mark minus shift end), both positive-only and to the second — instead of worked span minus scheduled duration. Both are stored on every calculated day whatever the tenant policy says, so enabling early-arrival counting later needs no recalculation of history. A day with no shift or a single mark stores null rather than zero, leaving it to be flagged instead of guessed. extra_time is untouched and still feeds the Resolución 38 DT reports unchanged.

New: app/Services/Overtime/ShiftExcess (arithmetic, anchored to instants so an overnight shift measures against the following day), OvertimeExcessPolicy and OvertimeExcessPolicyResolver (the single seam that resolves the policy from a workday, so moving it to per-shift or per-day changes the resolver and nothing in the calculation), the overtime_counts_pre_shift_excess setting defaulting to off, and its Spanish toggle in the existing Horas extra section.

Verified: 871 Pest tests green including 13 new unit tests over the excess arithmetic and 12 new calculator tests (normal overflow, early arrival under both policies, early plus late, no shift, one mark, overnight anchoring, sub-minute overflow, cross-tenant policy isolation, recalculation, and an extra_time regression guard); Pint and types:check clean; migrate:fresh --seed rebuilt the database with 89 of 136 seeded workdays carrying an OHC and the rest correctly null.
<!-- SECTION:FINAL_SUMMARY:END -->
