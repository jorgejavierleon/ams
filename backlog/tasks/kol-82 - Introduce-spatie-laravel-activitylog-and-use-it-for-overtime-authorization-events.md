---
id: KOL-82
title: >-
  Introduce spatie/laravel-activitylog and use it for overtime authorization
  events
status: To Do
assignee: []
created_date: '2026-08-21 09:39'
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
- [ ] #1 spatie/laravel-activitylog is installed, its migration published and run, and logging is scoped consistently with the app's existing multi-tenancy (an activity is attributable to the correct organization)
- [ ] #2 OvertimeAuthorization::approve() and OvertimeAuthorization::revoke() each record their own activity log entry (actor, timestamp, and the decision's details: authorized_hours/compensation_type for approve, reason for revoke)
- [ ] #3 WorkdayPresenter::timeline() surfaces every logged decision on a workday's overtime as its own chronological entry, so approving a day and later revoking it shows both events rather than only the latest
- [ ] #4 The existing OvertimeAuthorization columns (reviewed_by/reviewed_at/reason, revoked_by/revoked_at/revoked_reason) keep their current behaviour unchanged — the activity log is additive, not a replacement of the queryable current-state columns
- [ ] #5 Pest tests cover: approve-then-revoke produces two distinct, correctly ordered timeline entries; each logged entry carries the correct actor and reason/details
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
