---
id: KOL-91
title: >-
  Reject-document endpoint for the mobile app on POST
  /api/v1/me/documents/{document}/reject
status: Done
assignee: []
created_date: '2026-08-30 20:17'
updated_date: '2026-08-31 11:33'
labels:
  - mobile-api
dependencies: []
references:
  - kolvi-mobile KMO-45
  - kolvi-mobile src/features/documentos/document-reader-screen.tsx
  - kolvi-mobile docs/prd-mobile-app.md §D-F6-a
priority: medium
ordinal: 69000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-45 (Rechazar con motivo) needs to wire the sticky Rechazar button that document-reader-screen.tsx already stubs as a no-op — the reject flow is currently blocked, since routes/api.php explicitly notes reject is out of scope and tracked separately as KMO-45, while /send-code and /sign already exist (KOL-90). This ports the web self-service portal's reject action (My\DocumentController::reject(), reusing RejectDocument unchanged) to /api/v1 the way KOL-90 ported send-code/sign, mirroring Api\DocumentsController's own shape and its existing authorizeAccess() exactly.

RejectDocument's own abort_unless() only checks that the signer has a currently Pending signature row — it does not consult actionableSignatureFor()'s ordered-signing turn-blocking the way sendCode/sign do. That is existing behaviour already reachable from the web route today, not something this ticket changes; port it as-is and flag back to the user if kolvi-mobile's design turns out to assume otherwise.

Nothing in RejectDocument tracks a rejection timestamp (only rejection_reason, signed_ip, signed_user_agent on the signature row) — do not invent one, the same principle KOL-90 followed for the missing folio. The response carries only what the action actually tracks.

## User stories for manual testing (Gherkin)

This is API-only infrastructure for the KMO-45 mobile UI (a different repo); ams itself renders no new screen for it, so the "human can do and observe" step is a REST client against the running app rather than a browser. The Gherkin below exercises the endpoint directly with a signed-in employee's Sanctum token; kolvi-mobile's own Maestro flow is the vertical-slice, UI-level test once it consumes this endpoint.

```
Feature: Reject a document from the mobile API

  Scenario: An employee rejects a document awaiting their signature, with a reason
    Given an employee with a document whose signature is still pending
    When they POST /api/v1/me/documents/{document}/reject with {"reason": "No estoy de acuerdo."}
    Then the response is 200 with a bare {status: "rejected", document_status: "rejected"}
    And a GET on /api/v1/me/documents/{document} afterwards shows the document's status as rejected

  Scenario: An employee rejects a document with no reason given
    Given an employee with a document whose signature is still pending
    When they POST /api/v1/me/documents/{document}/reject with an empty body
    Then the response is 200 and the rejection is recorded with no reason

  Scenario: A document with more than one signatory is rejected by one of them
    Given a pending contract with both an employee and a legal representative signature
    When the employee rejects it
    Then the document's status becomes rejected and the legal representative's own still-pending signature is cancelled, not left pending

  Scenario: Rejecting a document that isn't the employee's to act on
    Given a document that neither belongs to the employee nor lists them as a signatory
    When they POST .../reject
    Then the response is 403
```
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 POST /api/v1/me/documents/{document}/reject is gated on permission:SignOwn:Document and the same ownership/signatory authorization sendCode()/sign() apply (Api\DocumentsController::authorizeAccess()) — 403 when the document belongs to neither the employee nor lists them as a signatory, 404 for an unknown or other-org id
- [x] #2 Accepts an optional reason (nullable string, max 500 chars) and calls RejectDocument::handle() unchanged, passing the authenticated request's IP and user agent as the rejection evidence, exactly as My\DocumentController::reject() does
- [x] #3 A reason longer than 500 characters returns a 422 validation error on the reason field, and the document/signature are left unchanged
- [x] #4 A signer with no currently Pending signature on the document (already signed, already rejected, or cancelled) gets a 403, per RejectDocument's own abort_unless — ported as-is, not softened into a sent:false-style response
- [x] #5 A successful rejection marks the signer's own signature Rejected with the stated reason (null when omitted), cancels every other still-pending signature on the document, and transitions the document itself to Rejected — mirroring RejectDocument's existing behaviour exactly, with no duplicated logic in the new controller method
- [x] #6 The response is a bare object {status, document_status} — no signed_at/rejected_at or invented folio, since RejectDocument tracks neither
- [x] #7 POST me/documents/{document}/reject is registered in routes/api.php under permission:SignOwn:Document, and the routes/api.php comment noting reject as out of scope for kolvi-mobile is removed
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Api\DocumentsController::reject() — authorizeAccess($request, $document); validate {reason: nullable|string|max:500}; call app(RejectDocument::class)->handle($document, $user, ip, userAgent, $validated['reason'] ?? null) via constructor injection like sign()'s SignDocument param; re-query the user's own signature for its new status; return response()->json(['status' => ..., 'document_status' => $document->status->value]) — $document is mutated in place by the action, same as sign().
2. routes/api.php — register POST me/documents/{document}/reject under permission:SignOwn:Document, next to send-code/sign; remove the comment noting reject as out of scope for kolvi-mobile (KOL-90's comment block).
3. tests/Feature/Api/DocumentsApiTest.php — new tests mirroring the sign block: 401/403/404 auth+authz, reason max:500 -> 422 with no state change, no pending signature -> 403 (not a soft response), successful reject with/without reason marks the signer's own signature Rejected + reason, cancels other pending signatures, sets document Rejected, response shape {status, document_status} only.
4. vendor/bin/pint --dirty --format agent; sa test --compact --filter=DocumentsApiTest; sa test --compact --filter=Document for regressions.
<!-- SECTION:PLAN:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Ported My\DocumentController::reject() to POST /api/v1/me/documents/{document}/reject via Api\DocumentsController::reject(), reusing RejectDocument unchanged and mirroring sign()'s shape exactly. Route registered under permission:SignOwn:Document; full Pest coverage added for authz, validation, no-pending-signature 403, and success (with/without reason, multi-signatory cancellation).
<!-- SECTION:FINAL_SUMMARY:END -->
