---
id: KOL-1
title: Spanish i18n for all UI strings
status: To Do
assignee: []
created_date: '2026-07-30 10:12'
labels:
  - module-auth
dependencies: []
references:
  - 'https://github.com/jorgejavierleon/ams/issues/45'
ordinal: 1000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Migrated from GitHub issue #45.

The old Filament app uses Laravel's `__()` translation helper for UI strings with a Spanish locale. The new React frontend must display all UI strings in Spanish (Resolución 38, Art. 3: system must be in Spanish). This covers auditing all hardcoded English strings in React components and replacing them with a proper i18n solution.

## Technical Notes

### Backend
- Ensure `config/app.php` `locale` is set to `es`
- Laravel validation messages: publish the lang files and ensure `resources/lang/es/` contains Spanish translations
- `HandleInertiaRequests::share()` can pass `locale` or `translations` to the frontend

### Frontend
- Option A (recommended): use Inertia's shared `translations` prop with a simple `t()` helper — avoids a separate i18n library
- Option B: install `react-i18next` with a Spanish translation JSON at `resources/js/locales/es.json`
- Audit all `resources/js/` components for English strings and replace

## Old App Reference
- Laravel lang: `../ams-filament/lang/es/`
- Filament translation overrides: `../ams-filament/lang/vendor/filament/`

Builds on the translatable i18n infrastructure from GitHub #57 (done); this audit verifies that infra is fully applied.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 An i18n solution is configured (Inertia shared translations or react-i18next)
- [ ] #2 All user-visible strings in React components are in Spanish
- [ ] #3 Error messages from Laravel validation are in Spanish
- [ ] #4 Date/time formatting follows Chilean locale (es-CL): dd/mm/yyyy, 24h clock
- [ ] #5 No English labels, placeholders, or button text visible in any panel
- [ ] #6 Pest test: server validation messages return Spanish strings
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
