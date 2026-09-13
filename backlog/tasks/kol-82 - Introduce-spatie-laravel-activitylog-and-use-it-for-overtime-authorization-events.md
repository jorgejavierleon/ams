---
id: KOL-82
title: >-
  Introduce spatie/laravel-activitylog and use it for overtime authorization
  events
status: Done
assignee:
  - jorgejavierleon@gmail.com
created_date: '2026-08-21 09:39'
updated_date: '2026-09-13 22:49'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-80
ordinal: 60000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Motivating bug: OvertimeAuthorization::approve() and revoke() (KOL-80) both write onto the same row (reviewed_by/reviewed_at/reason for approval, revoked_by/revoked_at/revoked_reason for revocation), and WorkdayPresenter::timeline() renders exactly one entry per row reflecting its *current* status. Approving a day's overtime and then revoking it therefore replaces the approval event in the Jornadas timeline with the revocation — a supervisor reviewing the day can no longer see that it was ever approved, by whom, or when, even though the approval columns are still sitting on the row unread by the presenter.

This is a specific case of a broader gap: several places in the app track 'current decision state' in plain columns rather than an append-only history of actions, so a record's past is destructively hidden (though not deleted) the moment it moves to its next state. Adopt spatie/laravel-activitylog (https://spatie.be/docs/laravel-activitylog/v5/introduction) as the standard mechanism for this going forward, starting with OvertimeAuthorization since it is the concrete case in hand. MarkModification's reviewer columns have a similar shape and are a natural next candidate, but are out of scope here — establish the pattern on one model first rather than migrating everything at once.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 spatie/laravel-activitylog is installed, its migration published and run, and logging is scoped consistently with the app's existing multi-tenancy (an activity is attributable to the correct organization)
- [x] #2 OvertimeAuthorization::approve() and OvertimeAuthorization::revoke() each record their own activity log entry (actor, timestamp, and the decision's details: authorized_hours/compensation_type for approve, reason for revoke)
- [x] #3 WorkdayPresenter::timeline() surfaces every logged decision on a workday's overtime as its own chronological entry, so approving a day and later revoking it shows both events rather than only the latest
- [x] #4 The existing OvertimeAuthorization columns (reviewed_by/reviewed_at/reason, revoked_by/revoked_at/revoked_reason) keep their current behaviour unchanged — the activity log is additive, not a replacement of the queryable current-state columns
- [x] #5 Pest tests cover: approve-then-revoke produces two distinct, correctly ordered timeline entries; each logged entry carries the correct actor and reason/details
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
1. Confirm spatie/laravel-activitylog is already installed/migrated (it is, from prior Document/LegalHourLimit work) and reuse the app's existing causer-based org attribution convention (no new activity_log schema/config needed).
2. OvertimeAuthorization::approve()/revoke() log their own activity() entry (event 'approved'/'revoked', causedBy the reviewer, properties: authorized_hours+compensation_type+reason for approve, reason for revoke).
3. Add OvertimeAuthorization::activities() morphMany relation.
4. Rework WorkdayPresenter::overtimeTimelineEntry() (singular, current-state) into overtimeTimelineEntries() (plural, one per logged Activity), keeping can_decide/can_revoke only on the latest entry.
5. Fix same-second ordering: add a sort_seq (row id) tiebreaker alongside sort_at in timeline() since created_at has only second precision.
6. Update WorkdayController::show() eager loads and the frontend OvertimeTimelineEntry type/doc accordingly.
7. Update/add Pest tests: two new OvertimeAuthorizationTest cases (activity logged on approve/revoke, with actor+details+ordering) and update the existing WorkdayOvertimeTest timeline assertion from 1 entry to 2.
8. Pint, Larastan (project-wide), and the affected test files all clean; full suite deferred per standing preference.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Discovered spatie/laravel-activitylog was already installed and used elsewhere (Documents, LegalHourLimit, PayrollExportHistory, Saas AuditLogController) with a causer-based org-attribution convention (no organization_id column on activity_log). Initially built a separate scoped App\Models\Activity + migration before finding this; reverted that in favor of matching the existing convention exactly.

A code-review pass (mattpocock-skills:code-review, forked) surfaced real issues, all fixed: (1) records approved/revoked before this deploy have no logged activity — timeline now falls back to synthesizing an entry from the still-present columns when no matching activity exists, so pre-existing history doesn't vanish (new test: 'a decision recorded before KOL-82 shipped...'); (2) authorized_hours/final_hours/calculated_hours are now snapshotted into each logged activity's properties (not read live off the row), so a hypothetical re-approve-after-revoke cycle can't retroactively rewrite an earlier entry's displayed figures; (3) can_decide/can_revoke are now computed once in timeline() and applied only to the single most-recent overtime entry after the full merge+sort, not per-entry inside overtimeTimelineEntries(); (4) the 'overtimeAuthorization.activities.causer:id,name' eager load used the colon column-shorthand on a MorphTo, which Laravel does not honor (only wheres merge across morph types, never the select) — switched to MorphTo::constrain(). Declined as out of scope/inconsistent with existing conventions: adding an organization_id column to activity_log (app already attributes activities to a tenant via the causer, e.g. Saas AuditLogController); using Spatie's Config::activityModel() instead of a hardcoded Activity::class import (every other activity() call site in the app hardcodes the import too); a shared causer-name-resolution helper (the same ternary already exists independently in 3 other controllers, pre-dating this ticket).

Full suite run after user confirmed the browser check (approve then revoke on a real Jornadas day showed both timeline entries): sa test --compact -> 1427 passed, 7 skipped (pre-existing, unrelated to this ticket), 0 failed.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added activity-log entries to OvertimeAuthorization::approve()/revoke() (event, causer, snapshotted hours/compensation_type/reason) and reworked WorkdayPresenter::timeline() to render one entry per logged decision instead of one summary-of-current-state entry, so approving then revoking a day shows both events. Pre-existing decisions with no logged activity fall back to a column-derived entry so history isn't lost. Verified with: 2 new + 1 updated Pest test, full project-wide Larastan (0 errors), Pint clean, npm run types:check (touched files clean), full sa test --compact suite (1427 passed, 7 pre-existing skips, 0 failed), and a manual browser check confirmed by the user (approve then revoke on a real Jornadas day showed both timeline entries).
<!-- SECTION:FINAL_SUMMARY:END -->
