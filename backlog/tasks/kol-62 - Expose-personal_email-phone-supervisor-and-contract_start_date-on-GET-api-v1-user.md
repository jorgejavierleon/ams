---
id: KOL-62
title: >-
  Expose personal_email, phone, supervisor and contract_start_date on GET
  /api/v1/user
status: Done
assignee:
  - '@claude'
created_date: '2026-08-12 19:09'
updated_date: '2026-08-12 21:21'
labels:
  - api
  - mobile
dependencies: []
priority: medium
ordinal: 43000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-51 builds a read-only Mis datos screen and needs these four fields on the wire. All exist on the User model already (personal_email, phone, supervisor via supervisor_id, contract_start_date); UserResource just doesn't expose them yet. Same shape as KOL-61, which did this for position and premise a day earlier.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/v1/user returns personal_email and phone as the raw column values, null when unset
- [x] #2 GET /api/v1/user returns supervisor as the related user's name, null when supervisor_id is unset
- [x] #3 GET /api/v1/user returns contract_start_date as a naive Y-m-d string, null when unset
- [x] #4 The route eager-loads supervisor alongside position and premise rather than lazy-loading it
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
vendor/bin/pint --dirty --format agent: clean. tests/Feature/Api/UserApiTest.php: 9/9 passing (two new tests added for the four fields' presence and their null case, plus the eager-load bound raised from 6 to 7 for the added supervisor relation), run via host PHP 8.4 against the already-running mysql container (DB_HOST overridden to 127.0.0.1 in this worktree's own .env copy) since this worktree has no Sail stack of its own — compose.yaml bind-mounts '.', which resolves to the primary checkout, not this worktree. 'sa test --compact' (DoD #2) could not be run as literally specified for the same reason; 'php artisan test' for the full suite hits an unrelated memory_limit ceiling in this environment — artisan shells out to a fresh PHP subprocess for the actual run that does not inherit '-d memory_limit' from the parent invocation, so raising it on the outer call has no effect. Confirmed this is environment-only and not a regression: the same failure is unrelated to UserResource/routes/api.php, and the targeted suite that exercises them is fully green. DoD #3 is not applicable — no TypeScript touched.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
GET /api/v1/user now returns personal_email, phone, supervisor (the related user's name) and contract_start_date (Y-m-d), all null when unset, with supervisor eager-loaded alongside position and premise. Verified with tests/Feature/Api/UserApiTest.php (9/9) and Pint; the full suite and 'sa test' itself could not be run in this worktree (no bound Sail stack), see notes.
<!-- SECTION:FINAL_SUMMARY:END -->
