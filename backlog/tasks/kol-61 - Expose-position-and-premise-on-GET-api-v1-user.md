---
id: KOL-61
title: Expose position and premise on GET /api/v1/user
status: Done
assignee: []
created_date: '2026-08-11 21:05'
updated_date: '2026-08-11 21:11'
labels: []
dependencies: []
documentation:
  - docs/prd-mobile-app.md
ordinal: 42000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
kolvi-mobile KMO-25 (Profile screen and menu) needs the identity header to read "{position} · {premise}" — the employee's job title and their assigned premise. UserResource today returns id, name, first_name, last_name, rut, email, avatar, permissions only; User already has position()/premise() BelongsTo relations (Position/Premise both have a plain string `name`) but neither is exposed over the API.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/v1/user returns position as the related Position's name, or null when the employee has no position_id
- [x] #2 GET /api/v1/user returns premise as the related Premise's name, or null when the employee has no premise_id
- [x] #3 The route eager-loads both relations so the request stays at one query rather than N+1
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
1. app/Http/Resources/UserResource.php — add position and premise, sourced from the existing position()/premise() BelongsTo relations' name column, nullable.
2. routes/api.php — the GET /v1/user closure loads both relations with loadMissing before wrapping, so the resource never triggers a lazy load.
3. tests/Feature/Api/UserApiTest.php — extend the existing identity test with the null case (no position/premise set), extend the field-allowlist test, add a test asserting the related names round-trip, and a loose query-count bound guarding against a regression to lazy loading.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
GET /api/v1/user now returns position and premise as the related Position/Premise name, null when unset. Route eager-loads both with loadMissing before wrapping in UserResource.

Verified: vendor/bin/sail artisan test --filter=UserApiTest --compact (7/7), full suite 930/934 passed 4 pre-existing skips, vendor/bin/pint --dirty clean. composer types:check has 2 pre-existing failures in WorkdayCalculator.php unrelated to this change (confirmed present on master before this branch).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
UserResource gained position and premise, sourced from User's existing position()/premise() BelongsTo relations (Position/Premise both have a plain name column), null when the employee has neither assigned. The /v1/user route eager-loads both before building the resource. Contract for kolvi-mobile KMO-25's identity header.
<!-- SECTION:FINAL_SUMMARY:END -->
