---
id: KOL-90
title: >-
  Verification-code sign endpoints for the mobile app on POST
  /api/v1/me/documents/{document}/send-code and /sign
status: To Do
assignee: []
created_date: '2026-08-27 12:43'
labels:
  - mobile-api
dependencies: []
references:
  - kolvi-mobile KMO-44
  - kolvi-mobile src/features/documentos/document-reader-screen.tsx
  - kolvi-mobile docs/design-decisions.md §8
priority: medium
ordinal: 68000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-44 (the firma electrónica simple flow) needs to wire the sticky Firmar documento button that document-reader-screen.tsx already stubs as a no-op — the sign flow is currently blocked, since the mobile API only exposes GET /api/v1/me/documents and GET /api/v1/me/documents/{document} (KOL-86/KOL-88). This ports the web self-service portal's send-code/sign actions (My\DocumentController::sendCode()/sign(), reusing SendVerificationCode and SignDocument unchanged) to /api/v1 the way KOL-86 and KOL-88 ported the list and detail. Confirmed missing today: no send-code or sign route registered under me/documents in routes/api.php; those actions exist only on the session-authenticated web routes, gated by permission:SignOwn:Document. Reject (My\DocumentController::reject()) is explicitly out of scope — kolvi-mobile tracks that separately as KMO-45. Note: KMO-44's description mentions the success screen showing a folio; nothing in ams computes a folio for documents today (only the punch-mark comprobante has one, per docs/design-decisions.md §3). Do not invent one — return what SignDocument already tracks (status, signed_at) and let kolvi-mobile flag back if the design genuinely needs a document folio.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 POST /api/v1/me/documents/{document}/send-code is gated on permission:SignOwn:Document and the same ownership/signatory authorization My\DocumentController::sendCode() applies — 403 when the document belongs to neither the employee nor lists them as a signatory, 404 for an unknown or other-org id
- [ ] #2 send-code accepts an optional resend boolean (default false) mapped to SendVerificationCode::handle()'s $force — a plain request reuses a live code per the class's own dedupe, an explicit resend always mints and emails a new one, matching the '15-minute expiry, reused unless explicitly resent' behaviour KMO-44 describes
- [ ] #3 send-code returns a bare object {sent: bool, expires_at: string|null} — expires_at as the naive Y-m-d H:i:s wall-clock string MarkResource already uses, present whenever sent is true so the app can render the countdown and offer resend; sent is false (not an error) when the signer has no actionable signature on this document right now (already signed, rejected, or not yet their turn under ordered signing), with no code minted or emailed in that case
- [ ] #4 POST /api/v1/me/documents/{document}/sign is gated on permission:SignOwn:Document with the same ownership/signatory authorization, accepts {code: string}, and calls SignDocument::handle() unchanged — passing the authenticated request's IP and user agent as the signing evidence, exactly as My\DocumentController::sign() does
- [ ] #5 A missing, wrong, or expired code returns a 422 with a validation error on the code field (SignDocument's own ValidationException), and does not sign, update signature status, or send any completion email
- [ ] #6 A correct, live code signs: the response is a bare object with the signature's new status, signed_at (Y-m-d H:i:s), and the document's own status (so the app can show 'fully signed' immediately when this was the last outstanding signature, without a second fetch of GET /me/documents/{document})
- [ ] #7 Existing SignDocument behaviour is unchanged and covered as a side effect: completing the last pending signature freezes the document as signed, generates and stores the signed PDF, and emails the employee — no duplicated logic in the new controller method
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
