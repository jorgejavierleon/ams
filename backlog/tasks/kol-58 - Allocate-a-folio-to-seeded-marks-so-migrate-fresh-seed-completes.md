---
id: KOL-58
title: 'Allocate a folio to seeded marks so migrate:fresh --seed completes'
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-08 16:43'
updated_date: '2026-08-08 16:49'
labels:
  - seeder
dependencies: []
priority: high
type: bug
ordinal: 39000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Running migrate:fresh --seed fails in WorkdaySeeder with 'Field folio doesn t have a default value'.

KOL-35 added marks.folio as NOT NULL with a unique index per organization, and MarkObserver::creating allocates it through Folio::allocate. WorkdaySeeder::mark() deliberately runs with model events muted — DatabaseSeeder mutes them — and hand-sets the guarded columns instead, reproducing the observer checksum formula. It was never updated to also allocate a folio, so every seeded mark violates the NOT NULL constraint.

Nobody caught it because the test suite does not run seeders and nobody had rebuilt a database since KOL-35 landed.

The checksum half of that method is fine: the seeder computes user id + type + ISO timestamp, which is exactly MarkObserver::checksumInput for a mark that was not captured offline. Only the folio is missing.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 WorkdaySeeder allocates a real folio for every mark it creates, through the same Folio::allocate the observer uses, so seeded folios are per organization and per day like any other
- [x] #2 migrate:fresh --seed completes without error on an empty database
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Fixed in database/seeders/WorkdaySeeder.php: the mark() helper now calls Folio::allocate with the employee organization and the punch time, the same allocator MarkObserver uses, so seeded receipts are numbered per organization and per day rather than carrying a placeholder.

No Pest test, per the project convention of not testing seeders. Verified by running migrate:fresh --seed to completion: 201 marks seeded, none without a folio, numbering as expected (20260727-0001, -0002, -0003 for organization 1).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
WorkdaySeeder::mark() now allocates a real folio through Folio::allocate, the same allocator MarkObserver uses. The seeder runs with model events muted, so the observer never fired and every seeded mark violated the NOT NULL folio column KOL-35 introduced — migrate:fresh --seed had been broken since. Verified by running migrate:fresh --seed to completion: 201 marks seeded, none without a folio, numbered per organization and day (20260727-0001 onwards). No Pest test, per the project convention of not testing seeders — DoD #4 is left unchecked for that reason rather than overlooked.
<!-- SECTION:FINAL_SUMMARY:END -->
