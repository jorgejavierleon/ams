---
id: KOL-39
title: >-
  Run the overtime calculation as an idempotent queued job that can never emit
  an approved figure
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-06 02:50'
updated_date: '2026-08-09 14:24'
labels:
  - overtime
  - backend
  - domain
milestone: m-2
dependencies:
  - KOL-38
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 400
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The calculation engine must be able to run, re-run and be trusted, and it must be structurally incapable of producing a payable figure on its own. PRD section 7.2: *"Never writes directly to an approved state. The output of this calculation can reach pending review at most."*

That sentence is the whole point of this task. It is not a UI convention or a code-review habit — the engine has no write path to `approved`, so no future refactor, backfill or console command can create a payable hour without a human decision behind it.

What lands here:
- The engine runs as a queued job after shift close-out, per organization and date, and can be re-run for a date range without duplicating or double-counting.
- Re-running is idempotent: the same inputs produce the same record, and a changed input (a corrected mark, a newly approved leave, a reassigned shift) updates the calculated value while leaving any human decision already attached to that day intact and visibly stale.
- The record links back to the raw marks it was derived from, so the traceability chain in KOL-49 has something to walk.

Kolvi already has the row the PRD calls `WorkdayCalculation`: `app/Models/Workday.php`, produced by `WorkdayCalculator`. Extend that rather than introducing a parallel daily table — a second row per employee per day would immediately drift out of sync with the one the DT reports read. The state machine belongs on the separate authorisation record in KOL-11, not on the workday.

Recalculation already has a trigger pattern worth following: `app/Observers/LeaveObserver.php` fires workday recalculation on leave status or range changes, and `WorkdayCalculator::recalculateWorkday()` recomputes a single row in place.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Calculated overtime is produced by a queued job for an organization and date, and the same job can be run over a date range for backfill
- [x] #2 There is no code path by which the calculation engine can write an approved or payable state; the highest state it can produce is pending review
- [x] #3 Re-running the job for a date already processed updates the calculated value rather than creating a second record, and produces an identical result when inputs are unchanged
- [x] #4 A corrected mark, a newly approved leave or a changed shift assignment causes the affected day to be recalculated
- [x] #5 When a day already carries a human decision and its calculated value changes, the decision is preserved and the record is surfaced as needing re-review rather than being silently overwritten or silently kept
- [x] #6 The calculated record can be traced back to the specific marks it was derived from
- [x] #7 The job is organization-scoped and processing one tenant never touches another
- [x] #8 Pest tests cover a first run, an idempotent re-run, a re-run after a mark correction, a re-run of a day already decided, and tenant isolation
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
1. Migration: add overtime_state (enum-cast string), overtime_calculated_at, overtime_decided_at, overtime_decided_value to workdays, plus an (organization_id, overtime_state) index for the pending-review queue.
2. Enum App\\Enums\\OvertimeCalculationState with exactly two cases — NotApplicable and PendingReview. No approved case exists, so the engine's cast makes an approved write impossible (AC2). The pending/approved/objected state machine stays on KOL-11's authorisation record.
3. WorkdayCalculator: make the bulk pass idempotent (upsert on the existing unique(user_id, date) instead of skipping computed days), scope it by organization and optional user ids, stamp overtime_state/overtime_calculated_at, and keep the decision columns out of the write set entirely. Split into calculateDate() (creates missing rows) and recalculateComputedDate() (touches only days already computed).
4. Job App\\Jobs\\CalculateOvertime: queued, one organization, a date range (single date by default), optional user ids, optional only-computed-days mode. No ShouldBeUnique — dropping a dispatch would let a second change go unprocessed.
5. Listener App\\Listeners\\RecalculateWorkdays on the existing WorkdaysRecalculationNeeded event (today a documented no-op): groups the affected users by organization and dispatches the job in only-computed-days mode. Wires up leave and shift-assignment changes; mark corrections already recalculate through MarkModificationManager.
6. Command overtime:calculate (--organization, --from, --to; defaults to yesterday for every organization), scheduled daily after close-out.
7. Workday: casts, sourceMarks() for the KOL-49 traceability walk, overtimeNeedsReReview() and a NULL-safe needsOvertimeReReview scope so a changed figure under an existing decision surfaces instead of being silently overwritten or silently kept.
8. Pest: first run, idempotent re-run, re-run after a mark correction, re-run of a decided day, tenant isolation, backfill range, and the impossibility of an approved engine output.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Design decisions worth carrying forward:

- **The 'no approved figure' guarantee is the enum, not a convention.** `App\Enums\OvertimeCalculationState` has exactly two cases (`not_applicable`, `pending_review`) and `workdays.overtime_state` casts to it, so an approved write throws a ValueError at the cast rather than being caught in review. The pending/approved/objected state machine is deliberately absent — that belongs to KOL-11's authorisation record.
- **KOL-11 handoff.** Two columns were added for a human decision to land on: `overtime_decided_at` and `overtime_decided_value`. The engine's write set (`WorkdayCalculator::calculatedAttributes()`) excludes them by construction, which is what preserves a decision across every recalculation. KOL-11 should stamp them when an authorisation is decided, and may later replace them with a relation to the authorisation record — the engine's contract is only 'never touch them'.
- **Re-review is derived, never stored.** `Workday::overtimeNeedsReReview()` and the `needsOvertimeReReview` scope compare the decided figure to the current one with MySQL's NULL-safe `<=>`, so a day that had overtime when it was decided and has none now (a correction leaving one mark) still surfaces. A `!=` would silently drop exactly that case.
- **Idempotency is the upsert on `unique(user_id, date)`.** The bulk pass no longer skips computed days; it upserts, and the update column list is read off the payload itself so it can never drift from what the engine writes. Deliberately not `ShouldBeUnique`: dropping an overlapping dispatch would drop a correction nobody reprocesses.
- **Event-driven recalculation only touches days already computed.** `recalculateComputedDate()` vs `calculateDate()`. Otherwise a shift assignment backdated a month would manufacture a month of absences from an admin's edit. Backfill is the `overtime:calculate` command's job.
- **Listener discovery gotcha.** Laravel auto-discovers listeners in `app/Listeners`; adding `Event::listen()` in AppServiceProvider as well ran every recalculation twice (caught by a queue assertion, and it doubled full-suite runtime from 162s to 325s). Verified with `sa event:list`.
- Pre-existing Larastan error in `ShiftExcess::forWorkdayRow()` (`object` param, undefined `$date`) fixed by narrowing to `\stdClass`, which is what both call sites pass. phpstan is now clean.
- Mark corrections were already covered: `MarkModificationManager` calls `recalculateWorkday()` directly, and raw marks are immutable by PRD §9, so no MarkObserver trigger was added.

Follow-up during review: the listener-discovery duplication was **pre-existing**, not introduced here. `StampMarkModificationNotifiedAt` was both auto-discovered and registered manually in `AppServiceProvider::boot()`, so it ran twice per notification (harmless only because its `notified_at === null` guard makes it idempotent). Confirmed against the Laravel 13 events docs and `Illuminate\Foundation\Support\Providers\EventServiceProvider::shouldDiscoverEvents()` (`$shouldDiscoverEvents = true` by default, scanning `app/Listeners`). Removed the manual registration; `sa event:list` now shows one `@handle` binding for each of the two listeners. AppServiceProvider registers no listeners at all now, with a comment saying why, and the rule is recorded as its own 'Events & listeners' section in docs/architecture.md.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
The overtime engine now runs as App\Jobs\CalculateOvertime — queued, scoped to one organization, over a date or a range — and structurally cannot emit a payable figure: workdays.overtime_state casts to App\Enums\OvertimeCalculationState, whose only cases are not_applicable and pending_review, so an approved write throws at the cast rather than being caught in review. WorkdayCalculator's bulk pass became idempotent (upsert on the existing unique(user_id, date) instead of skipping computed days), gained organization and user scoping, and routes both write paths through a single calculatedAttributes() write set that excludes the new overtime_decided_at/overtime_decided_value columns — which is what preserves a human decision across every recalculation, with staleness derived NULL-safely by overtimeNeedsReReview()/needsOvertimeReReview(). WorkdaysRecalculationNeeded finally has a consumer (RecalculateWorkdays), so approved leaves and shift-assignment changes recalculate days already computed, one job per tenant; overtime:calculate backfills and is scheduled daily at 04:00. Verified by 21 new Pest tests in tests/Feature/CalculateOvertimeJobTest.php covering a first run, an idempotent re-run, a re-run after a mark correction, a decided day whose figure moves and one whose figure disappears, tenant isolation on both create and re-run, range backfill and the command; full suite 892 tests / 888 passed / 4 pre-existing skips, plus pint, phpstan and tsc clean.
<!-- SECTION:FINAL_SUMMARY:END -->
