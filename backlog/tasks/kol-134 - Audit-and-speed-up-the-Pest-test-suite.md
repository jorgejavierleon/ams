---
id: KOL-134
title: Audit and speed up the Pest test suite
status: Done
assignee: []
created_date: '2026-09-26 10:21'
updated_date: '2026-10-12 09:56'
labels: []
dependencies: []
priority: medium
ordinal: 139000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The Pest suite has grown to ~1,550 tests and a full run now takes 5-6+ minutes (observed during KOL-133.3), which slows down every ticket's local feedback loop and CI. Audit whether that test count and runtime are actually earning their keep: profile which files/tests are slowest, look for redundant or low-value coverage (e.g. near-duplicate scenarios across sibling test files, unnecessary RefreshDatabase/seeding overhead, HTTP-level tests that could be unit-level), and cut the total wall-clock time without losing meaningful coverage.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A profiling pass identifies the slowest test files/tests (e.g. via Pest's --profile or PHPUnit's test timing) and the report is shared or logged
- [x] #2 At least the top time-cost offenders are addressed (trimmed, merged, converted to a cheaper test type, or given lighter setup) with justification for each change
- [x] #3 Any test removed or merged is justified as truly redundant, not just slow
- [x] #4 The full suite's wall-clock time is measurably reduced from the pre-change baseline
- [x] #5 sa test --compact still passes after the changes
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Jorge (2026-10-11): Don't write/keep Pest tests for migrations themselves (e.g. OrganizationOwnershipTest, CompanyToCostCenterMigrationTest) — a migration runs once in the project's lifetime and isn't logic that changes or needs ongoing testing. These are confirmed slow offenders from profiling (60s+ and 15s+ per test) and should be removed/trimmed as part of this ticket, not preserved.

Result: baseline full-suite run (PAO_DISABLE=1 php artisan test --profile) was 1705 tests / 490.09s, with the two slowest offenders being OrganizationOwnershipTest's three migration-replay tests (~62.8s each) and CompanyToCostCenterMigrationTest's seven migration-replay tests (~15.6s each) — together ~300s of the 490s total. Removed the three migration-replay tests + their rewindOwnerColumn/replayOwnerMigration helpers from OrganizationOwnershipTest.php (kept its non-migration isOwner/TransferOrganizationOwnership/Gate::before tests), and deleted CompanyToCostCenterMigrationTest.php entirely (every test in it replayed the KOL-32 migrations). After: 1695 tests / 209.45s (1688 passed, 7 skipped, 0 failed) — a 57% reduction. New slowest test is Tests\Feature\Api\DocumentsApiTest at 17.38s, an order of magnitude below the old worst case; top-10 slow tests now total only 31.41s (15% of suite time), so no other offender rises to the same severity. pint --dirty clean; sa test --compact passes.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Removed the migration-replay Pest tests (OrganizationOwnershipTest's 3 backfill tests + all of CompanyToCostCenterMigrationTest, 10 tests total) since migrations run once and aren't logic that needs ongoing coverage. Full suite dropped from 490.09s to 209.45s (57% faster), all remaining tests still pass.
<!-- SECTION:FINAL_SUMMARY:END -->
