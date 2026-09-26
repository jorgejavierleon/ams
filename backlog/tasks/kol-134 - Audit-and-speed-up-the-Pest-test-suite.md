---
id: KOL-134
title: Audit and speed up the Pest test suite
status: To Do
assignee: []
created_date: '2026-09-26 10:21'
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
- [ ] #1 A profiling pass identifies the slowest test files/tests (e.g. via Pest's --profile or PHPUnit's test timing) and the report is shared or logged
- [ ] #2 At least the top time-cost offenders are addressed (trimmed, merged, converted to a cheaper test type, or given lighter setup) with justification for each change
- [ ] #3 Any test removed or merged is justified as truly redundant, not just slow
- [ ] #4 The full suite's wall-clock time is measurably reduced from the pre-change baseline
- [ ] #5 sa test --compact still passes after the changes
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
