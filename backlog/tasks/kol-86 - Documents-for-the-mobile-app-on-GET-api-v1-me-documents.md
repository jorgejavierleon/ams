---
id: KOL-86
title: Documents for the mobile app on GET /api/v1/me/documents
status: To Do
assignee: []
created_date: '2026-08-25 02:09'
updated_date: '2026-08-25 02:09'
labels:
  - mobile-api
dependencies: []
references:
  - kolvi-mobile KMO-42
  - kolvi-mobile src/features/documentos/documents-api.ts
ordinal: 64000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-42 (Documentos tab list) needs the employee's own non-draft documents — those belonging to them or listing them as a signatory — with a status badge and the awaiting_me flag that drives the pending-signature count and the tab-bar badge. My\DocumentController already has this whole flow for the web self-service portal (index() with its status/signatory scoping, Document::actionableSignatureFor()); this ports the list half to /api/v1 the way KOL-81 ported the leaves list. Confirmed missing today: /api/v1/me/documents is not registered at all in routes/api.php, unlike /me/leaves and /me/workdays which are. kolvi-mobile's client (documents-api.ts) was built against this ticket's own reading of the contract below and will need adjusting wherever the real implementation disagrees.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 GET /api/v1/me/documents returns the authenticated employee's own non-draft documents — status != draft and (user_id = employee OR employee is a signatory) — gated on permission:ViewOwn:Document, mirroring My\DocumentController::index()'s scope exactly
- [ ] #2 Each entry carries id, title, status_label (Document status->label()), status_badge (status->badge(): published/signed->success, pending_signature->warning, rejected/voided->destructive, draft/archived->neutral, matching docs/design-decisions.md's tone table on the kolvi-mobile side), published_at as Y-m-d, and awaiting_me (Document::actionableSignatureFor($user) !== null) — already accounts for ordered signing, so a signatory whose turn has not come is not counted
- [ ] #3 The list is a bare {data: [...]} envelope (a DocumentResource::collection(...)), no pagination parameter, matching how KOL-81 and the other /me/* mobile lists chose not to paginate even though the web table does
- [ ] #4 Results are ordered the same way My\DocumentController::index defaults (published_at desc, id desc); an employee with no visible documents gets an empty array
- [ ] #5 OPEN DECISION, do not assume it away: kolvi-mobile's own reading flattens status into status_label/status_badge only (no raw status value, since KMO-42 needs no client-side status logic beyond the badge and awaiting_me) — confirm that is enough, or that a future ticket (the reader, KMO-43) needs the raw value/type/my_signature fields the web Inertia response also carries, and add them without breaking this list's own shape
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
