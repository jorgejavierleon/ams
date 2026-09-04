---
id: KOL-105
title: Scope ImportRun access to its owning user
status: To Do
assignee: []
created_date: '2026-09-04 11:19'
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
- [ ] #1 import_runs has a non-nullable user_id foreign key to users
- [ ] #2 CreateImportRunFromUpload records the creating user's id on the new ImportRun
- [ ] #3 A request for an ImportRun owned by a different user in the same organization gets 404, not 403, consistent with the existing cross-org 404 behavior documented on ImportWizardController::show()
- [ ] #4 The fix is expressed so it automatically covers the wizard routes KOL-100 through KOL-103 will add (strategy/match-key, preview, commit, error-report download), not something each of those tickets has to re-implement
- [ ] #5 Existing show/updateMapping tests still pass; a new test asserts a second user in the same org cannot reach another user's ImportRun
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
