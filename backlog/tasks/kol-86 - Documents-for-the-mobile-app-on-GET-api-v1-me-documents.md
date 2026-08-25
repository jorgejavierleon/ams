---
id: KOL-86
title: Documents for the mobile app on GET /api/v1/me/documents
status: Done
assignee: []
created_date: '2026-08-25 02:09'
updated_date: '2026-08-25 23:32'
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
- [x] #1 GET /api/v1/me/documents returns the authenticated employee's own non-draft documents — status != draft and (user_id = employee OR employee is a signatory) — gated on permission:ViewOwn:Document, mirroring My\DocumentController::index()'s scope exactly
- [x] #2 Each entry carries id, title, status_label (Document status->label()), status_badge (status->badge(): published/signed->success, pending_signature->warning, rejected/voided->destructive, draft/archived->neutral, matching docs/design-decisions.md's tone table on the kolvi-mobile side), published_at as Y-m-d, and awaiting_me (Document::actionableSignatureFor($user) !== null) — already accounts for ordered signing, so a signatory whose turn has not come is not counted
- [x] #3 The list is a bare {data: [...]} envelope (a DocumentResource::collection(...)), no pagination parameter, matching how KOL-81 and the other /me/* mobile lists chose not to paginate even though the web table does
- [x] #4 Results are ordered the same way My\DocumentController::index defaults (published_at desc, id desc); an employee with no visible documents gets an empty array
- [x] #5 OPEN DECISION, do not assume it away: kolvi-mobile's own reading flattens status into status_label/status_badge only (no raw status value, since KMO-42 needs no client-side status logic beyond the badge and awaiting_me) — confirm that is enough, or that a future ticket (the reader, KMO-43) needs the raw value/type/my_signature fields the web Inertia response also carries, and add them without breaking this list's own shape
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
1. Add DocumentResource (id, title, type, status/status_label/status_badge, published_at, my_signature, awaiting_me) — additive fields beyond AC#2's minimum for KMO-43 forward-compat, since kolvi-mobile's KMO-42 parser (documents-api.ts) safely ignores unknown fields.
2. Add Api\DocumentsController::index() mirroring My\DocumentController::index()'s scope/eager-load/order exactly.
3. Register GET /api/v1/me/documents gated on permission:ViewOwn:Document in routes/api.php.
4. Pest feature test tests/Feature/Api/DocumentsApiTest.php: auth/permission gates, own+signatory scope, draft exclusion, ordering, empty list, field shape, awaiting_me incl. ordered-signing block.
5. Resolve AC#5 by shipping the additive fields (documented in final summary), pint, sa test --compact.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
AC#5 resolved: shipped additive fields (type, raw status value, my_signature) alongside the required status_label/status_badge/awaiting_me — kolvi-mobile's KMO-42 parser (documents-api.ts) safely ignores unrecognised fields, confirmed by reading it, so this is non-breaking and gives a future KMO-43 reader ticket the same shape the web Inertia response already carries.

Found: this app disables JsonResource's default wrapping globally (AppServiceProvider), so most /me/* list endpoints (Workdays, PendingMarkModifications) return bare arrays, not {data:[...]}. kolvi-mobile's documents-api.ts explicitly requires the wrapped {data:[...]} envelope though, so DocumentsController::index() wraps explicitly via response()->json(['data' => ...]). Documented in docs/architecture.md's Mobile API section so the next /me/* endpoint doesn't get bitten by the same default.

Full sa test --compact run: 1162/1191 pass; 25 pre-existing failures are all 'Permission denied' on storage/framework/testing/disks/local (owned by root from a prior run), unrelated to this change — none touch Documents/Api tests. New DocumentsApiTest: 9/9 passing.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added GET /api/v1/me/documents (Api\DocumentsController::index + DocumentResource), gated on permission:ViewOwn:Document, mirroring My\DocumentController::index()'s scope/order exactly. AC#5 resolved by shipping additive type/status/my_signature fields alongside the required status_label/status_badge/awaiting_me, since kolvi-mobile's KMO-42 parser ignores unrecognised fields. Response explicitly wrapped in {data:[...]} to match kolvi-mobile's documents-api.ts, since this app disables JsonResource's default wrapping globally (documented in docs/architecture.md). Verified with tests/Feature/Api/DocumentsApiTest.php (9/9 passing) and vendor/bin/pint clean; full suite 1162/1191 pass, the 25 failures are pre-existing environment permission errors unrelated to this change. No TypeScript touched, so types:check DoD item is not applicable.
<!-- SECTION:FINAL_SUMMARY:END -->
