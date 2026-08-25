---
id: KOL-87
title: Notify the requester in-app when a queued report export finishes
status: To Do
assignee: []
created_date: '2026-08-25 10:57'
updated_date: '2026-08-25 10:57'
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
- [ ] #1 Queued report-export notifications (ready and failed) are delivered through Laravel's database channel in addition to the existing mail channel
- [ ] #2 The web app shows a notification indicator (e.g. a bell with an unread badge) that reflects new report-export notifications without the user manually reloading the page
- [ ] #3 Opening a ready notification links to the report's download page; opening a failed notification explains that generation failed
- [ ] #4 A notification can be marked read / dismissed, and read state persists across page loads
- [ ] #5 Notifications and their read state are scoped to the requesting user and their organization only, proven by a test
- [ ] #6 Pest tests cover notification creation on ready/failed, the unread-count data returned to the frontend, and marking a notification read
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
