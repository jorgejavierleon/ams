---
id: KOL-87
title: Notify the requester in-app when a queued report export finishes
status: Done
assignee: []
created_date: '2026-08-25 10:57'
updated_date: '2026-10-11 10:05'
labels:
  - payroll-reports
  - backend
  - frontend
dependencies:
  - KOL-16
ordinal: 65000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
KOL-16 delivers queued report-export outcomes by email only (ReportExportReady / ReportExportFailed notifications, mail channel). That's fine as a floor, but a user who is actively working in the app right now has no way to know their export finished without leaving to check their inbox — the old Filament app's admin panel had a live in-app notification bell for exactly this kind of background-job outcome, and this ticket brings that back.

Add Laravel's `database` notification channel (new `notifications` table migration — does not exist in this app yet) to the existing ReportExportReady and ReportExportFailed notifications from KOL-16, and surface them in the web app UI as a notification indicator (bell/badge) with an unread count, without requiring a manual page reload. Laravel has first-class support for this via broadcasting (Echo + a broadcaster such as Reverb or Pusher) so a finished export can push to the browser the moment the queue job completes, instead of only appearing on the next full page load; no broadcasting driver is installed in this app yet (BROADCAST_CONNECTION=log, no Reverb/Pusher/laravel-echo in composer.json or package.json), so introducing one is in scope if that's the direction taken — call out the choice and its cost (new dependency, a running broadcast server in dev/deploy) in the task's notes before committing to it. A simpler polling-based unread-count refresh is an acceptable fallback if broadcasting infrastructure is judged too heavy for what's otherwise a low-frequency notification.

This should generalize to whichever screen actually triggers a queued export by the time this is picked up — today that's only the DT compliance-reports panel (KOL-16), but the payroll reports section (KOL-18 onward) will be the more common real-world user of this once it ships.

## User stories for manual testing (Gherkin)

Scenario: See a live notification when my queued export is ready
  Given I am logged in and I requested a report export that was queued
  When the background job finishes rendering the file
  Then a notification indicator in the app updates to show one unread notification, without me reloading the page
  And opening it shows a link that takes me to download the report

Scenario: See a live notification when my queued export fails
  Given I am logged in and I requested a report export that was queued
  When the background job fails to render the file
  Then a notification indicator in the app updates to show one unread notification, without me reloading the page
  And opening it tells me the export failed

Scenario: Dismiss a notification I've already seen
  Given I have an unread report-export notification
  When I open or dismiss it
  Then it no longer counts toward my unread badge
  And it still appears if I look at my full notification history

Scenario: Notifications never leak across organizations
  Given a report export belonging to another organization just finished
  When I check my notifications
  Then I do not see any notification for it
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Queued report-export notifications (ready and failed) are delivered through Laravel's database channel in addition to the existing mail channel
- [x] #2 The web app shows a notification indicator (e.g. a bell with an unread badge) that reflects new report-export notifications without the user manually reloading the page
- [x] #3 Opening a ready notification links to the report's download page; opening a failed notification explains that generation failed
- [x] #4 A notification can be marked read / dismissed, and read state persists across page loads
- [x] #5 Notifications and their read state are scoped to the requesting user and their organization only, proven by a test
- [x] #6 Pest tests cover notification creation on ready/failed, the unread-count data returned to the frontend, and marking a notification read
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
1. Add `notifications` table migration (sail artisan make:notifications-table).
2. Add `database` channel + toArray() to ReportExportReady/ReportExportFailed (generic kind/url payload, decoupled from report type so future payroll-report exports reusing these notifications work automatically).
3. NotificationController: index() (recent notifications + unread count, scoped via $request->user()->notifications()), read() (mark one as read, scoped the same way -> 404 for another user's). Plain response()->json(), matching CommuneController convention — no API Resource class needed for this internal widget.
4. Routes under auth:web,dt (both guards share the User model/provider) so the bell works in both the main app and the DT panel.
5. Frontend: NotificationBell component (Popover + Badge, shadcn) polling GET /notifications every ~20s via plain fetch+setInterval (NOT Inertia usePoll/router, which would re-run the current page's full controller logic on every tick — too heavy for report pages). Mark-as-read via fetch POST with X-XSRF-TOKEN read from the cookie Laravel already queues (PreventRequestForgery), since this needs to not reload/remount the page (the whole point of the ticket) — Inertia's router.post/back() would remount the layout per click.
6. Mount NotificationBell in AppSidebarHeader (covers payroll reports + rest of main app) and DtLayout's header (covers the only current export trigger, KOL-16's DT reports).
7. Pest tests: database channel delivery for ready/failed, GET /notifications scoping (own vs other user/org), POST read scoping + idempotency.
8. Decision: chose polling over broadcasting (Echo/Reverb) — no broadcasting driver installed, this is a low-frequency event, and a new always-running broadcast server is not justified for a per-export notification. Documented per ticket's own acceptance of this fallback.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified live in the browser by the user against the dev Sail stack (DT panel queued export -> bell badge updated without reload -> clicked through to the signed download page -> marked-read persisted across reload). Caught and fixed one real issue during that pass: the notifications table migration had only run against the Pest test DB (RefreshDatabase), not the actual dev DB, so the first live notification failed in the queue (missing table) and landed in failed_jobs. Fixed by running 'sail artisan migrate' and re-notifying the already-ready export; queue:flush cleared the stale failed job.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added the database notification channel to ReportExportReady/ReportExportFailed (generic kind+url payload, independent of report type, so any future screen reusing these notifications — e.g. payroll reports — appears in the bell automatically). Added a lightweight JSON NotificationController (index/read) scoped through the authenticated notifiable, routed under auth:web,dt so both the main app and DT panel share it. Added a NotificationBell component (Popover+Badge) polling that endpoint every 20s via plain fetch — not Inertia's router/usePoll, to avoid re-running the current page's full controller on every tick — mounted in AppSidebarHeader and DtLayout. Chose polling over broadcasting per the task's own cost/benefit callout: no Echo/Reverb/Pusher dependency or always-running broadcast server for a low-frequency event. Verified with 6 new Pest tests (database delivery, unread-count/list scoping, mark-read scoping and idempotency) plus a live manual pass in the browser.
<!-- SECTION:FINAL_SUMMARY:END -->
