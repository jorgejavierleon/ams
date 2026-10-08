---
id: KOL-146
title: 'Spike: how do Talana, Buk and GeoVictoria model turnos rotativos'
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-10-06 09:12'
updated_date: '2026-10-06 22:52'
labels:
  - spike
  - shifts
dependencies: []
type: spike
ordinal: 172000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Research how three Chilean HR/workforce-management competitors — Talana, Buk and GeoVictoria — let employers configure rotating shifts (turnos rotativos: 4x4, 7x7, day/night alternation and similar cycles common in mining, retail and healthcare), compared to what Kolvi actually has today.

Why this matters now: Kolvi already has ShiftType::Rotational and ShiftType::Cyclic enum cases (app/Enums/ShiftType.php), but ShiftScheduleResolver and ShiftDay currently model a shift as a single weekly pattern keyed by weekday (app/Models/ShiftDay.php, app/Services/ShiftScheduleResolver.php) — it is not yet clear whether that is enough to represent a true multi-week rotating cycle, or whether those two types are currently just labels with no real rotation logic behind them. Before investing in building out genuine rotation-cycle support, understand what the market already offers and expects, and what Kolvi actually has versus what it merely names.

This is research only. Do not build anything from this task — its output is a written finding and a recommendation, not code.

What this spike should settle:
- For each of Talana, Buk and GeoVictoria: how does their product let an admin configure a rotating shift pattern (a fixed cycle length like 4x4/7x7, a custom N-day cycle, day/night alternation, or a fully manual per-employee calendar)? Use public docs, marketing pages, help-center articles, and demo videos; note plainly wherever something could not be verified without a logged-in trial account.
- What data model does each competitor's UI imply (a repeating N-day pattern anchored to a start date, versus a hand-placed calendar with no underlying pattern)?
- Whether Kolvi's ShiftType::Rotational / ::Cyclic distinction already maps onto working rotation logic today, verified by reading Shift.php, ShiftDay.php and ShiftScheduleResolver.php — not assumed from the enum names alone.
- A gap analysis: what, if anything, Kolvi is missing today to compete on rotating-shift support.
- A go / no-go / defer recommendation, with reasoning.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Findings on how Talana, Buk, and GeoVictoria each let an employer configure a rotating shift pattern are written down, sourced from public docs/demos/marketing pages, with anything that could not be verified without a logged-in account noted as such
- [x] #2 Kolvi's current ShiftType::Rotational/::Cyclic implementation is checked against the real code (Shift, ShiftDay, ShiftScheduleResolver) and documented as either a working rotation feature or a label with no rotation logic yet
- [x] #3 A gap analysis comparing Kolvi's current rotating-shift capability to the three competitors' is recorded
- [x] #4 A go/no-go/defer recommendation is written with its reasoning; if go, candidate follow-up tasks are named but not created or implemented as part of this spike
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
1. Read Shift.php, ShiftDay.php, ShiftScheduleResolver.php, ShiftType.php to determine whether Rotational/Cyclic are working rotation logic or labels only.
2. Delegate public-source research on Talana/Buk/GeoVictoria rotating-shift UX to a background research agent, output to docs/research/turnos-rotativos-competitor-research.md.
3. Write gap analysis comparing Kolvi's current capability to the three competitors.
4. Write go/no-go/defer recommendation with reasoning and named (not created) candidate follow-ups.
5. Record findings in the task and finalize.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Internal code finding (acceptance criterion #2): confirmed by reading app/Models/Shift.php, app/Models/ShiftDay.php, app/Services/ShiftScheduleResolver.php, app/Enums/ShiftType.php.

- ShiftType::Rotational and ::Cyclic are enum cases with NO behavioural branching anywhere in the codebase. grep for 'ShiftType::' outside the enum file shows only: ShiftController (options list + enum validation rule), Dt/ReportController (options list + validity check for DT journal export), ShiftChangesReportService (checks === Fixed), DailyReportService (checks === Exceptional). Nothing checks for ::Rotational or ::Cyclic anywhere.
- Shift has no cycle_length, cycle_anchor_date, or rotation-offset column/field at all (see @Fillable list and @property docblock in Shift.php) — there is no place to even store 'this is an 8-day 4x4 cycle starting 2026-01-01'.
- ShiftDay rows are keyed by a plain integer weekday (0=Mon..6=Sun) OR a specific calendar date (for exceptional one-off days), never by 'day N of an M-day cycle'.
- ShiftScheduleResolver::resolveDate() resolves a date purely via $date->dayOfWeekIso and matches ShiftDay::weekday — a single fixed weekly pattern. There is no concept of a cycle start date, cycle length, or 'which day of the rotation is this calendar date'.
- Conclusion: Rotational/Cyclic are currently pure UI labels with zero rotation logic behind them. Building real turnos rotativos support (4x4, 7x7, etc.) requires new data model concepts (cycle length + anchor date, or a pattern template) and resolver changes — it is not a small gap, it's a new mechanism.

Gap analysis (acceptance criterion #3), built from docs/research/turnos-rotativos-competitor-research.md plus the internal code finding already in these notes:

1. No cycle-length concept exists anywhere in Kolvi's data model. Shift has no cycle_length field; ShiftDay is keyed only by a plain weekday (0-6) or a single override date. Talana and Buk both center their rotating-shift feature on one explicit field ("Cantidad de días del ciclo" / "cantidad de días que durará el turno") that Kolvi has no equivalent of.
2. No per-assignment cycle anchor/offset exists. Talana ("día de inicio dentro del ciclo") and Buk ("Día que comienza a repetirse el ciclo") both let the SAME rotating-shift template be assigned to different employees starting at different points in the cycle (Talana's documented staffing example: two workers on the same 7x7 shift, one starting day 1, another day 8, to stagger coverage). Kolvi's ShiftAssignment.start_date only says *when* an assignment begins, not *which cycle day* it begins on — there's no field for that distinction today.
3. No resolver logic computes "day N of an M-day cycle for date D." ShiftScheduleResolver::resolveDate() only ever computes ISO weekday and matches it against ShiftDay::weekday. This is new logic to write, not an extension of existing logic.
4. Non-7-divisible cycles (4x4 = 8 days, etc.) cause the "on" block to drift relative to weekdays — Talana documents this outright ("no hace referencia a días de la semana sino a días de ciclo"), and GeoVictoria's FAQ is full of customer support pain about exactly this (several worked examples telling admins to manually pick a Monday as cycle-start or rest days end up misaligned with weekends). Any Kolvi design must treat this as a first-class concern, not an edge case — it visibly bites even mature competitors' customers.
5. Day/night alternation is NOT a separate feature in either Talana or Buk — it's just that different cycle-days within the same rotating template carry different schedules (including night schedules). Kolvi should follow that precedent: one generic per-cycle-day schedule mechanism covers 4x4, 7x7, day/night alternation, etc., rather than a separate "alternating" type.
6. No vendor offers a selectable "4x4"/"7x7" preset template — all three use a free-entry cycle-length field with those names only as documentation examples. Kolvi should not build a fixed preset catalog; a generic N-day-cycle authoring mechanism is what the market actually ships.
7. Buk explicitly blocks bulk-Excel assignment for rotating shifts (must use a dedicated screen); Talana explicitly provides a manual non-cyclical Excel escape hatch for shifts that don't follow a clean cycle. Kolvi doesn't yet have either path and would need a product decision here if built.

Recommendation (acceptance criterion #4): DEFER.

Reasoning: ShiftType::Rotational/::Cyclic exist today as enum labels only (see prior note) — nothing is currently broken or regressing, so there's no urgency from existing behavior. Building real rotation support is not a small patch: it requires (a) a new cycle-length field on Shift, (b) a new per-employee cycle-anchor/offset field on ShiftAssignment, (c) new ShiftDay semantics keyed by cycle-day instead of (or in addition to) weekday, (d) new resolver logic in ShiftScheduleResolver to compute day-of-cycle per date and handle non-7-divisible drift correctly, and (e) new admin UI to author a cycle template. That matches the shape of what all three competitors actually built — this is a genuine new mechanism, not a label-to-logic wiring job. Going now, without a specific paying customer or sales commitment needing 4x4/7x7 support, risks building ahead of demand on a feature the market shows is easy to get subtly wrong (see GeoVictoria's weekday-drift support burden). Recommend deferring until a specific customer/vertical (mining, retail, healthcare) need is confirmed, at which point re-open with the competitor research above as a starting reference.

If/when this becomes a GO, candidate follow-up tickets (named only, not created per spike scope):
- Add a cycle-length field to Shift and a cycle-anchor/offset field to ShiftAssignment for rotational/cyclic shifts.
- Extend ShiftDay (or a new model) to store a per-cycle-day schedule instead of/alongside the current per-weekday schedule.
- Teach ShiftScheduleResolver to resolve a date against a cycle (day-of-cycle math, including correct handling of cycles that don't divide evenly into 7).
- Build the admin UI for authoring a rotating-shift cycle template and choosing each employee's cycle-start offset at assignment time.
- Decide whether rotating-shift assignment goes through bulk import/Excel or a dedicated screen only (Buk disallows the former; Talana offers a separate manual-per-day escape hatch for non-cyclical edge cases) — product decision needed before building.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Research spike, no code changed. Verified by reading Shift.php/ShiftDay.php/ShiftScheduleResolver.php/ShiftType.php that Rotational/Cyclic are enum labels with zero rotation logic (ShiftDay is keyed only by weekday or a single override date; resolver only matches ISO weekday — no cycle length/anchor concept exists anywhere). Competitor research on Talana, Buk, GeoVictoria (public docs/FAQ only, cited source-by-source, login-gated claims flagged) written to docs/research/turnos-rotativos-competitor-research.md: all three use a free-entry cycle-length field (no 4x4/7x7 presets) anchored to a per-assignment cycle-start offset, fold day/night alternation into the same per-cycle-day schedule mechanism, and Talana/GeoVictoria explicitly document the weekday-drift issue for cycles not divisible by 7. Gap analysis and a DEFER recommendation (with reasoning and named, not created, follow-up candidates) recorded in task notes. Definition-of-Done items (pint/tests/types-check/Pest) are not applicable — no PHP or TS files were changed in this spike.
<!-- SECTION:FINAL_SUMMARY:END -->
