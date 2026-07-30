---
id: KOL-2
title: 'Performance review: N+1 queries in resource tables (eager loading audit)'
status: To Do
assignee: []
created_date: '2026-07-30 10:13'
labels:
  - module-workdays
dependencies: []
references:
  - 'https://github.com/jorgejavierleon/ams/issues/55'
ordinal: 2000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Migrated from GitHub issue #55.

Resource tables that list employees, workdays, shifts, or documents are prone to N+1 query problems when relationships are not properly eager-loaded. The old Filament app uses `->with([])` in resources but the new app may have gaps. This is a systematic audit and fix pass before production.

## Technical Notes

### Backend
- Use `DB::enableQueryLog()` in tests or install `barryvdh/laravel-debugbar` (dev only) to detect N+1
- Fix pattern: add `->with(['relation1', 'relation2'])` to the index query in each controller
- Use `->withCount('relation')` instead of `count($model->relation)` in views

### Frontend
- No frontend changes needed

List pages all go through the shared `useServerTable` / `DataTable` foundation (GitHub #58) — check whether the audit is better done once at that layer than per-controller.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 All index (list) pages are audited for N+1 queries using Laravel Debugbar or DB::listen()
- [ ] #2 Employees index: avatar URL, position, premise, company loaded in one query each (no N+1)
- [ ] #3 Workdays index: user, shift, markIn, markOut, pendingModifications eager-loaded
- [ ] #4 Documents index: employee, signatures loaded
- [ ] #5 Leaves index: employee, approvedBy loaded
- [ ] #6 Any discovered N+1 is fixed with the appropriate ->with([]) or ->withCount([]) call
- [ ] #7 Pest test: assert query count stays under a threshold for list pages
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
