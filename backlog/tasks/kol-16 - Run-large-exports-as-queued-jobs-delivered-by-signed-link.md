---
id: KOL-16
title: Run large exports as queued jobs delivered by signed link
status: To Do
assignee: []
created_date: '2026-08-04 11:12'
labels:
  - payroll-reports
  - backend
  - frontend
milestone: m-0
dependencies:
  - KOL-15
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 15000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The non-functional requirements set a hard line: up to 500 employees per period must export in under 30 seconds synchronously, and anything larger must run as a queued job that notifies the user when the file is ready. Talana does the same thing by emailing the finished report.

This is greenfield in this codebase — **there is no `app/Jobs` directory at all**. The queue connection is already `database` (`config/queue.php`), and `app/Notifications` has five existing notifications to copy the shape from (`LeaveApproved.php` and friends). Whether the worker is running in this project's dev and deploy setup needs checking as part of this task; a queued export that nobody processes is worse than a slow synchronous one.

The security requirement is equally explicit: a generated payroll file contains sensitive data and must not sit at a guessable public URL. Use signed URLs with an expiry, and make sure the file is deleted or unreachable after it lapses. Serving from a non-public disk and streaming through an authorised route is acceptable and probably simpler than signed storage URLs; pick one and justify it in the notes.

The threshold between synchronous and queued should be a configuration value, not a magic number buried in a controller — different tenants and different reports have very different weights.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Exports below the configured threshold still return directly as a download with no queue round-trip
- [ ] #2 Exports above the threshold are dispatched to the queue and the user is told the report is being generated rather than being left with a hanging request
- [ ] #3 The user is notified when the file is ready, through the same notification mechanism the app already uses for leave and mark modifications
- [ ] #4 The finished file is reachable only through an authenticated or signed route with an expiry, and is not readable from a public URL at any point
- [ ] #5 Expired files are no longer downloadable and are cleaned up rather than accumulating on disk indefinitely
- [ ] #6 A user can only download an export belonging to their own organization, proven by a test attempting a cross-tenant download
- [ ] #7 The synchronous/asynchronous threshold is configurable
- [ ] #8 A failed export job surfaces the failure to the user instead of leaving them waiting for a notification that never arrives
- [ ] #9 The queue worker setup needed for this to run in development and in deployment is verified and documented in the task notes
- [ ] #10 Pest tests cover the below-threshold path, the queued path with the job faked and asserted, the notification, expiry, and the cross-tenant download attempt
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
