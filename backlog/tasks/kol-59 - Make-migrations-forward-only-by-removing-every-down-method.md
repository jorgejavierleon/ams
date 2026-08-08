---
id: KOL-59
title: Make migrations forward-only by removing every down method
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-08 16:44'
updated_date: '2026-08-08 16:49'
labels:
  - migrations
dependencies: []
priority: high
type: task
ordinal: 40000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Migrations in this project are forward-only. A migrate:refresh failed on the down() of the folio migration, which tried to drop a composite unique index InnoDB was using to satisfy the marks.organization_id foreign key. Reverse migrations are written blind, are effectively never executed, and rot silently until a rebuild needs them.

Every down() is removed. Laravel Migrator guards both call sites with method_exists, so a migration without one is safe and a rollback simply skips it.

Consequence to know: migrate:refresh and migrate:rollback are now structurally unusable — refresh clears the migrations table without dropping anything, then collides on create table. migrate:fresh is the only rebuild path and is unaffected, since it drops tables without consulting down() at all.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 No migration in database/migrations declares a down method
- [x] #2 Every migration file still parses, and the full Pest suite passes since it migrates from scratch
- [x] #3 migrate:fresh --seed rebuilds the database end to end
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
49 migrations stripped. Verified: zero 'function down' matches under database/migrations, every file parses under php -l, Pint clean, full Pest suite green at 871 tests (it migrates from scratch, so it exercises every up()), and migrate:fresh --seed rebuilt the development database end to end.

The folio migration down() that triggered this — dropping a composite unique index InnoDB needed for the marks.organization_id foreign key — is gone with the rest, so the foreign-key fix that had been drafted for it is no longer needed and no task was opened for it.

Recorded as a standing preference in the assistant's project memory: never write down(), and always reach for migrate:fresh rather than refresh or rollback.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Removed the down() method from all 49 migrations that declared one, making migrations forward-only. Laravel's Migrator guards both call sites with method_exists, so a migration without one is safe and a rollback simply skips it. This also retires the folio migration down() that started this — it tried to drop a composite unique index InnoDB needed for the marks.organization_id foreign key — so the foreign-key repair drafted for it was never needed.

Consequence now permanent: migrate:refresh and migrate:rollback are unusable (refresh clears the migrations table without dropping anything, then collides on create table). migrate:fresh is the only rebuild path and is unaffected.

Verified: zero 'function down' matches under database/migrations, every file parses under php -l, Pint clean, 871 Pest tests green (the suite migrates from scratch, exercising every up()), and migrate:fresh --seed rebuilt the development database end to end. No Pest test was added for the removal itself, so DoD #4 is left unchecked deliberately; the suite is the evidence.
<!-- SECTION:FINAL_SUMMARY:END -->
