---
id: KOL-16
title: Run large exports as queued jobs delivered by signed link
status: Done
assignee: []
created_date: '2026-08-04 11:12'
updated_date: '2026-08-25 11:06'
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
- [x] #1 Exports below the configured threshold still return directly as a download with no queue round-trip
- [x] #2 Exports above the threshold are dispatched to the queue and the user is told the report is being generated rather than being left with a hanging request
- [x] #3 The user is notified when the file is ready, through the same notification mechanism the app already uses for leave and mark modifications
- [x] #4 The finished file is reachable only through an authenticated or signed route with an expiry, and is not readable from a public URL at any point
- [x] #5 Expired files are no longer downloadable and are cleaned up rather than accumulating on disk indefinitely
- [x] #6 A user can only download an export belonging to their own organization, proven by a test attempting a cross-tenant download
- [x] #7 The synchronous/asynchronous threshold is configurable
- [x] #8 A failed export job surfaces the failure to the user instead of leaving them waiting for a notification that never arrives
- [x] #9 The queue worker setup needed for this to run in development and in deployment is verified and documented in the task notes
- [x] #10 Pest tests cover the below-threshold path, the queued path with the job faked and asserted, the notification, expiry, and the cross-tenant download attempt
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
1. config/reports.php (new): export.queue_threshold (env REPORT_EXPORT_QUEUE_THRESHOLD, default 500), export.link_expiry_minutes (env REPORT_EXPORT_LINK_EXPIRY_MINUTES, default 1440). Add both env vars to .env.example.
2. Migration + model: report_exports table (organization_id, user_id, type, format, filters json, status, disk_path, filename, failure_reason, expires_at, timestamps). App\Enums\ReportExportStatus (Pending/Processing/Ready/Failed). App\Models\ReportExport uses BelongsToOrganization + belongsTo(User) — auto-stamps organization_id via CurrentOrganization::id() same as every other org-scoped model.
3. ReportWriter: add excelBytes/pdfBytes/wordBytes (+ small captureOutput helper) returning raw string content, alongside the existing streaming methods (untouched, zero regression risk to the sync path).
4. DtReportExporter: extract the existing prepare() step (locale switch, Blade render, filename) out of download(), add renderToBytes(...): array{filename,mime,contents} that reuses it plus the new *Bytes writer methods. download() itself is refactored to call prepare() but its behavior/signature is unchanged.
5. app/Jobs/GenerateReportExport (ShouldQueue, Illuminate\Foundation\Queue\Queueable per CalculateOvertime convention): loads the ReportExport row, marks Processing, calls DtReportExporter::renderToBytes, writes to Storage::disk('local') (already private — storage_path('app/private')), marks Ready with expires_at = now()->addMinutes(config threshold), notifies the requesting user. failed() marks Failed + notifies failure.
6. Notifications (mail only, matching LeaveApproved shape exactly): ReportExportReady (link via signed route, expiry shown) and ReportExportFailed. New markdown views under resources/views/mail/reports/, lang keys in lang/{en,es}/mail.php.
7. DtReportController::export(): if count(workerIds) > config threshold, create ReportExport + dispatch GenerateReportExport, return response()->json(['queued'=>true,'message'=>...], 202); else unchanged synchronous path (AC #1 preserved exactly, existing DtReportExportTest keeps passing since default threshold is 500 and test fixtures use far fewer workers).
8. New download action (App\Actions\Reports\DownloadReportExport, mirroring the existing DownloadDocument action) + App\Http\Controllers\Dt\ReportExportDownloadController, routed inside the existing dt.reports. group (same auth:dt + dt_organization_selected middleware as documents.download) as GET reports/exports/{reportExport}/download, name dt.reports.exports.download, additionally gated by the signed middleware. Cross-tenant isolation comes from ReportExport's BelongsToOrganization global scope on the route-model binding (same mechanism as every other org-scoped resource) plus an explicit status/expiry check (404 if not Ready, 410 if expires_at has passed).
9. app/Console/Commands/PruneExpiredReportExports (signature report-exports:prune-expired, mirrors SweepExpiredOvertimeRestDayBalances): deletes the disk file + row for every expired ReportExport. Scheduled hourly in routes/console.php.
10. Frontend: resources/js/pages/dt/reports/export-buttons.tsx switches from plain <a href> to a click handler that fetches the export URL — a JSON content-type means queued (toast.info the message via sonner, matching the existing toast usage in use-flash-toast.ts), otherwise blob()+object URL triggers the download exactly as the anchor did before. New ui.dt.reports.export.queued/failed lang keys.
11. Tests (Pest, tests/Feature/): below-threshold path unchanged (existing suite), a queued-path test asserting Queue::fake()+assertPushed, a job test asserting the file lands on the private disk and status flips to Ready, a notification test (Notification::fake), an expiry test (410 past expiry, download unreachable), and a cross-tenant test (second dt-guard session with a different audited organization gets 404 on the first org's signed link) — using the existing seedExportableOrganization()/actingAs($inspector,'dt')->withSession(['dt_organization_id'=>...]) fixtures from DtReportExportTest.php.
12. Queue worker (AC #9): composer.json's "dev" script already runs `php artisan queue:listen` and this is confirmed actively running inside the Sail container right now (docker exec showed a live queue:listen process from `composer run dev`). compose.yaml has no dedicated queue-worker service, so under plain `sail up -d` (without composer run dev) nothing processes jobs — this gap is documented in the task notes rather than silently left, no compose.yaml change is made without user sign-off since it's an infra change.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Follow-up fix after manual QA: the emailed signed link originally pointed straight at the raw file response (dt.reports.exports.download). A fresh browser tab whose only navigation is a Content-Disposition:attachment response has no HTML to render — Chrome shows a blank page (address bar even reverts to about:blank on inspect) and only actually completes the download on a manual page refresh, which no end user would ever discover. Confirmed via curl that the backend itself was always correct (200, right headers, valid xlsx bytes) — this was purely a browser UX problem with linking directly to a binary response as the first thing a tab ever loads.

Fix: split the single signed download route into two positions in the same route group (Dt\ReportExportDownloadController):
- dt.reports.exports.show (GET reports/exports/{reportExport}, signed) — what the email actually links to now. Renders a small standalone Blade page (resources/views/dt/reports/export-ready.blade.php, styled with the app's own Tailwind tokens via @vite('resources/css/app.css'), no Inertia/React needed) with a "Descargar reporte" button, or an "enlace vencido" message if expires_at has passed.
- dt.reports.exports.download (GET reports/exports/{reportExport}/download, no longer signed) — what the button on that page links to. Still gated by auth:dt + dt_organization_selected + ReportExport's BelongsToOrganization global scope on the route-model binding, exactly like the existing documents.download route — AC #4 asks for an authenticated *or* signed route, and by the time a request reaches this action it has already passed both the signed landing page and session auth.

ReportExportReady notification now points at .show instead of .download. Tests updated/added in tests/Feature/QueuedReportExportTest.php: the landing page renders a real page with the download link (not a raw file), the landing page shows an expired message when lapsed while the download route itself still independently 410s, and the cross-tenant test now hits the (now-unsigned) download route by name directly. All 120 dt-group tests pass; full suite re-run pending. PHPStan/Pint clean.

Final verification: full suite 1178 passed / 4 skipped / 0 failed (docker exec ams-laravel.test-1 php artisan test --compact), PHPStan level 7 clean, Pint clean, tsc --noEmit clean. Added a dedicated test for report-exports:prune-expired (deletes expired file+row, leaves unexpired ones) to evidence AC #5's cleanup half, which the earlier expiry test only covered from the download-refusal side. Added a note to the ready-email (lang/{es,en}/mail.php report_export_ready.note) telling the recipient they must be logged in with the correct audited organization selected for the link to work, per user request during manual QA — verified by actually sending the notification and reading the rendered text back from Mailpit's API.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Large DT report-compliance exports now route through a configurable threshold (config('reports.export.queue_threshold'), default 500): below it, unchanged synchronous download; above it, dispatched to App\Jobs\GenerateReportExport, which renders via new byte-producing ReportWriter/DtReportExporter methods and saves to the private local disk. The requester is mailed a signed, expiring link once ready (or a failure notice if the job fails) matching the app's existing mail-notification shape. The mailed link lands on a real HTML landing page with a Descargar button rather than a raw file response — a mid-session manual QA round caught that linking directly to a binary response left a fresh browser tab blank until a manual refresh, which no user would discover on their own. The actual file route is authenticated + organization-scoped (via ReportExport's BelongsToOrganization global scope on the route-model binding), matching AC #4's authenticated-or-signed wording. report-exports:prune-expired runs hourly to delete expired files/rows. Queue worker: confirmed composer run dev's queue:listen is what processes jobs in this Sail setup today; no worker exists in compose.yaml or for a deployed environment, documented in docs/architecture.md and the task notes as a gap for whoever ships the next queued job to production. Verified with tests/Feature/QueuedReportExportTest.php (8 tests: threshold passthrough, queuing, job rendering+notifying, failure notifying, the landing page, expiry, cross-tenant isolation, and pruning) plus the full existing DtReportExportTest.php suite unaffected. Full suite 1178 passed/4 skipped/0 failed, PHPStan level 7 clean, Pint clean, tsc clean. Manually verified end-to-end through a real browser session (login, org selection, queued export, Mailpit email, landing page, download) with the user.
<!-- SECTION:FINAL_SUMMARY:END -->
