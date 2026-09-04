---
id: KOL-105
title: Scope ImportRun access to its owning user
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-04 11:19'
updated_date: '2026-09-04 11:34'
labels: []
dependencies: []
references:
  - app/Models/ImportRun.php
  - app/Http/Controllers/ImportWizardController.php
  - app/Models/ReportExport.php
priority: high
type: bug
ordinal: 92000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
import_runs only carries organization_id (BelongsToOrganization); it has no user_id. Every ImportWizardController route bound to {importRun} (show, updateMapping) therefore only checks org membership, so any user holding Import:Employee in the org can view and edit (via PATCH mapping) another user's in-progress or abandoned ImportRun just by editing the numeric id in the URL. This diverges from the ReportExport precedent KOL-94's map calls out to mirror, which does carry user_id for exactly this 'requester' concept. Found while reviewing navigation behavior after KOL-98/KOL-99 shipped.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 import_runs has a non-nullable user_id foreign key to users
- [x] #2 CreateImportRunFromUpload records the creating user's id on the new ImportRun
- [x] #3 A request for an ImportRun owned by a different user in the same organization gets 404, not 403, consistent with the existing cross-org 404 behavior documented on ImportWizardController::show()
- [x] #4 The fix is expressed so it automatically covers the wizard routes KOL-100 through KOL-103 will add (strategy/match-key, preview, commit, error-report download), not something each of those tickets has to re-implement
- [x] #5 Existing show/updateMapping tests still pass; a new test asserts a second user in the same org cannot reach another user's ImportRun
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
1. Migration: add non-nullable `user_id` FK (after organization_id, constrained, cascadeOnDelete) to import_runs.
2. New App\Models\Scopes\UserScope mirroring OrganizationScope: constrains queries to Auth::id(), no-op when unauthenticated (console/queue).
3. New App\Models\Concerns\BelongsToUser trait mirroring BelongsToOrganization: applies UserScope global scope, stamps user_id on creating from Auth::id(), exposes user(): BelongsTo. This is what makes AC #4 automatic for KOL-100..103's future routes bound to {importRun}.
4. ImportRun model: use BelongsToUser, add user_id to #[Fillable] and phpdoc.
5. ImportRunFactory: default user_id to User::factory().
6. Update ImportWizardTest's mappingRunFor() helper and the existing cross-org test to set user_id on the factory-created run so the legitimate owner's route-model-binding still resolves.
7. Add a new Pest test: a second user in the same org gets 404 on show and updateMapping for another user's ImportRun.
8. vendor/bin/pint --dirty, run ImportWizardTest + ImportRunTest filtered, then full suite at the end.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Migration add_user_id_to_import_runs_table (non-nullable FK, cascadeOnDelete) required migrate:fresh in dev since pre-existing rows had no owner (per project convention: forward-only migrations, no down()). New App\Models\Scopes\UserScope + App\Models\Concerns\BelongsToUser trait mirror OrganizationScope/BelongsToOrganization exactly: global scope filters every query to Auth::id(), no-op when unauthenticated (console/queue), auto-stamps user_id on creating. Applied to ImportRun alongside BelongsToOrganization, so every current and future route bound to {importRun} (KOL-100..103) 404s a cross-user request without each controller re-implementing the check. CreateImportRunFromUpload needed no code change -- user_id is stamped by the trait's creating hook exactly like organization_id already was. Updated ImportRunFactory to default user_id (User::factory()) and fixed existing test helpers (mappingRunFor, cross-org test) to set user_id so the legitimate owner's route-model-binding still resolves. Added: ImportWizardTest 'a second user in the same organization cannot view or update another user's ImportRun' (show + updateMapping both assertNotFound), and ImportRunTest 'an ImportRun is scoped to its user'. Verified: vendor/bin/pint --dirty clean; sail artisan test --compact --filter=ImportWizardTest (13/13 passed); --filter=ImportRunTest (3/3 passed); full suite sail artisan test --compact (1366 passed, 4 pre-existing skipped, 0 failed). npm run types:check shows 2 pre-existing errors in resources/js/pages/roles/{index,show}.tsx, unrelated to this change (no TS files touched by KOL-105).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
import_runs now has a non-nullable user_id FK, auto-stamped on creation and enforced by a new UserScope global scope (via a BelongsToUser trait mirroring the existing BelongsToOrganization/OrganizationScope pattern) applied to ImportRun. Route-model-binding on {importRun} therefore 404s a same-org, different-user request automatically -- covering show/updateMapping today and every future KOL-100..103 wizard route without per-route ownership checks. Verified with a new cross-user test (show + updateMapping both 404) plus a model-level scoping test; full Pest suite (1366 passed) and Pint both clean.
<!-- SECTION:FINAL_SUMMARY:END -->
