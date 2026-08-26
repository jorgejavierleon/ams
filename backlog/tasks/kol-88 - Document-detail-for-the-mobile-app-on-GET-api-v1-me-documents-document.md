---
id: KOL-88
title: 'Document detail for the mobile app on GET /api/v1/me/documents/{document}'
status: To Do
assignee: []
created_date: '2026-08-26 00:24'
updated_date: '2026-08-26 00:24'
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
- [ ] #1 GET /api/v1/me/documents/{document} returns the one document, gated on permission:ViewOwn:Document and the same ownership/signatory authorization My\DocumentController::show() applies (belongs to the employee or lists them as a signatory) — 403 otherwise, 404 when the id does not exist
- [ ] #2 The body carries id, title, status_label, status_badge (the same status->badge() tone mapping KOL-86 used for the list), body (DocumentVariableResolver::resolve() output — resolved HTML, never the raw template), published_at as Y-m-d or null, and awaiting_me (Document::actionableSignatureFor($user) !== null, same semantics as the list's own field and as the web page's my_signature.can_sign) — already accounts for ordered signing
- [ ] #3 The response is a bare object, not a {data: ...} envelope, matching day-detail and punch-receipt's own /me/* single-resource shape rather than the list's collection envelope
- [ ] #4 An employee with no visible documents, or a document that belongs to someone else and does not list them as a signatory, gets 403 rather than leaking whether the id exists
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
