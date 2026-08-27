---
id: KOL-88
title: 'Document detail for the mobile app on GET /api/v1/me/documents/{document}'
status: Done
assignee: []
created_date: '2026-08-26 00:24'
updated_date: '2026-08-27 00:56'
labels:
  - mobile-api
dependencies: []
references:
  - kolvi-mobile KMO-43
  - kolvi-mobile src/features/documentos/document-detail-api.ts
ordinal: 66000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-43 (the document reader) needs one document's resolved body plus the signature state that drives the sticky Rechazar / Firmar documento bar. My\DocumentController already has this whole flow for the web self-service portal (show(), with DocumentVariableResolver resolving the body and Document::actionableSignatureFor() driving can_sign); this ports the show half to /api/v1 the way KOL-86 ported the list half. Confirmed missing today: only GET /api/v1/me/documents (the list) is registered in routes/api.php; there is no {document} show route. kolvi-mobile's client (document-detail-api.ts) was built against this ticket's own reading of the contract below — a bare object, not a {data:...} envelope, matching how DocumentsController::index() had to opt IN to that wrapping (this app disables JsonResource's default wrapping globally, per docs/architecture.md's Mobile API section) — and will need adjusting wherever the real implementation disagrees.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/v1/me/documents/{document} returns the one document, gated on permission:ViewOwn:Document and the same ownership/signatory authorization My\DocumentController::show() applies (belongs to the employee or lists them as a signatory) — 403 otherwise, 404 when the id does not exist
- [x] #2 The body carries id, title, status_label, status_badge (the same status->badge() tone mapping KOL-86 used for the list), body (DocumentVariableResolver::resolve() output — resolved HTML, never the raw template), published_at as Y-m-d or null, and awaiting_me (Document::actionableSignatureFor($user) !== null, same semantics as the list's own field and as the web page's my_signature.can_sign) — already accounts for ordered signing
- [x] #3 The response is a bare object, not a {data: ...} envelope, matching day-detail and punch-receipt's own /me/* single-resource shape rather than the list's collection envelope
- [x] #4 An employee with no visible documents, or a document that belongs to someone else and does not list them as a signatory, gets 403 rather than leaking whether the id exists
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
1. Add GET /api/v1/me/documents/{document} route gated on permission:ViewOwn:Document
2. Add DocumentsController::show() mirroring My\DocumentController::show()'s ownership/signatory authorization (403), route-model-binding org scope gives 404 for unknown/other-org ids
3. Add DocumentDetailResource (bare object): id, title, status_label, status_badge, body (DocumentVariableResolver::resolve()), published_at, awaiting_me
4. Pest tests in tests/Feature/Api/DocumentsApiTest.php mirroring the existing index tests' style
5. Pint + sa test --compact
<!-- SECTION:PLAN:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added GET /api/v1/me/documents/{document} (DocumentsController::show + DocumentDetailResource), mirroring My\DocumentController::show()'s ownership/signatory 403 and returning a bare object with the resolved body and awaiting_me. Verified with 8 new Pest tests in DocumentsApiTest (auth, 403 cross-employee, 404 unknown id, own/signatory visibility, field shape, bare-object response, awaiting_me) plus the full suite: 1225 tests, 1221 passed, 4 skipped, 0 failures. pint --dirty clean. No TypeScript touched (mobile client is KMO-43 in kolvi-mobile).
<!-- SECTION:FINAL_SUMMARY:END -->
