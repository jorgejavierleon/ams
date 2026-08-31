---
id: KOL-92
title: >-
  Signed-PDF download URL for the mobile app on GET
  /api/v1/me/documents/{document}/pdf
status: Done
assignee: []
created_date: '2026-08-31 15:03'
updated_date: '2026-08-31 18:43'
labels:
  - mobile-api
dependencies: []
references:
  - kolvi-mobile KMO-46
  - kolvi-mobile src/features/documentos/document-reader-screen.tsx
  - kolvi-mobile src/features/documentos/document-detail-api.ts
ordinal: 70000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-46 needs to let an employee open their document's signed PDF once it exists. The mobile app decided against downloading bytes into app storage or embedding a PDF viewer; it opens the file with the OS's own handler via Linking.openURL, which means the URL itself must authorize the request — an external browser cannot attach the app's Sanctum bearer token. Confirmed missing today: the mobile /api/v1/me/documents endpoints (KOL-86/KOL-88/KOL-90) expose no signed-PDF field or route at all. The only existing download path, My\DocumentController::download(), is session-authenticated web/Inertia and unusable from the app. Document::SIGNED_MEDIA_COLLECTION is populated by SignDocument::completeIfFullySigned() once every signature is collected (app/Actions/Documents/SignDocument.php). The nearest existing pattern for a signed, time-limited link is KOL-16's report-export email (URL::temporarySignedRoute, routes/web.php), though that route also requires a DT web session on top of the signature — this one must authorize on the signature alone, since the destination is an external browser with no session or bearer token at all. Needs two things: (1) a Sanctum-authenticated JSON endpoint the app calls first, mirroring the existing me/documents authorization, that mints a short-lived signed URL for a specific document; (2) a separate route, guarded only by Laravel's signed-URL middleware (no Sanctum, no permission gate), that streams the actual PDF bytes for that signed URL to be opened.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A Sanctum-authenticated GET on the documents-detail route family (e.g. /api/v1/me/documents/{document}/pdf-url) returns a JSON {url, expires_at} when the document's SIGNED_MEDIA_COLLECTION has a file, using the same ownership/signatory authorization as show() -- 403 for a document belonging to neither the employee nor a signatory, 404 for an unknown or other-org id
- [x] #2 The same endpoint returns a distinct not-ready response (not a 404) when the document exists and is accessible but has no signed PDF yet (not fully signed), so the app can tell 'not generated yet' apart from 'wrong document'
- [x] #3 The minted URL is a Laravel signed route with a short expiry (a few minutes), generated with URL::temporarySignedRoute
- [x] #4 Requesting the streaming route with a valid, unexpired signature returns the PDF bytes (Content-Type: application/pdf) with no Authorization header and no active session on the request at all -- only the signature authorizes it
- [x] #5 Requesting the streaming route with a missing, tampered, or expired signature is rejected (403) even though no Sanctum token is presented, and never falls back to any other auth check
- [x] #6 The streaming route serves the same file My\DocumentController::download() already serves (getFirstMedia(Document::SIGNED_MEDIA_COLLECTION)), unchanged content
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented: (1) GET /api/v1/me/documents/{document}/pdf-url — Sanctum-authenticated, permission:ViewOwn:Document, same authorizeAccess() ownership/signatory check as show(); returns {url, expires_at} via URL::temporarySignedRoute (5 min expiry) when SIGNED_MEDIA_COLLECTION media exists, else 409 {message, code: pdf_not_ready}. (2) GET /api/v1/me/documents/{document}/pdf — public route (outside auth:sanctum, only 'signed' middleware, no permission gate), streams the same media via response()->file() with Content-Type: application/pdf. Both actions added to Api\DocumentsController (pdfUrl/pdfShow). Added es/en lang keys ui.documents.api.pdf_not_ready. Verified with 12 new Pest tests in tests/Feature/Api/DocumentsApiTest.php covering 401/403/404, not-ready 409, signed-URL minting, valid/missing/tampered/expired signature streaming (403 on all three failure modes), and byte-identical output vs My\DocumentController::download(). sa test --compact --filter=DocumentsApiTest: 56/56 passed. pint --dirty clean. No TypeScript touched, so DoD #3 left unchecked (not applicable).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a Sanctum-authenticated GET /api/v1/me/documents/{document}/pdf-url that mints a 5-minute URL::temporarySignedRoute (or 409 pdf_not_ready) and a public, signed-only GET /api/v1/me/documents/{document}/pdf that streams the same signed-PDF media My\DocumentController::download() serves. Verified with 12 new Pest tests (401/403/404, not-ready 409, signed-URL minting, and streaming with valid/missing/tampered/expired signatures) — sa test --compact --filter=DocumentsApiTest: 56/56 passed; pint clean.
<!-- SECTION:FINAL_SUMMARY:END -->
